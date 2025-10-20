import sys
import os
import argparse
from dotenv import load_dotenv
from fastapi import FastAPI
from pydantic import BaseModel
import numpy as np
import openai
import requests
from pathlib import Path
import atexit

# 导入向量存储模块
from vector_store import create_vector_store

# 加载.env文件
# 首先检查当前目录下的.env文件
current_env_path = Path('.env')
if current_env_path.exists():
    load_dotenv(dotenv_path=current_env_path)
else:
    # 如果当前目录没有.env文件，则尝试加载上级目录的.env文件
    parent_env_path = Path('../backend/.env')
    if parent_env_path.exists():
        load_dotenv(dotenv_path=parent_env_path)

# 配置OpenAI客户端（默认配置，可通过参数或环境变量覆盖）
openai.api_key = os.getenv('AI_API_KEY', '')
api_base = os.getenv('AI_API_URL', 'https://api.openai.com/v1')
if api_base != 'https://api.openai.com/v1':
    openai.api_base = api_base

# 获取模型配置
embedding_model = os.getenv('AI_MODEL', 'text-embedding-ada-002')

app = FastAPI()

# 初始化向量存储
vector_store = None

# 定义默认存储配置
DEFAULT_STORE_CONFIG = {
    'type': 'disk',  # 可选: memory, disk, milvus
    'disk': {
        'index_path': 'data/faiss_index.index',
        'ids_path': 'data/doc_ids.npy',
        'texts_path': 'data/doc_texts.txt'
    },
    'milvus': {
        'host': 'localhost',
        'port': 19530,
        'collection_name': 'vectors'
    }
}

class DocInput(BaseModel):
    doc_id: int
    text: str

class QueryInput(BaseModel):
    text: str
    top_k: int = 5

# 兼容OpenAI格式的接口请求
def get_embedding(text):
    try:
        # 尝试使用OpenAI官方SDK方式
        resp = openai.Embedding.create(model=embedding_model, input=text)
        return np.array(resp['data'][0]['embedding']).astype('float32')
    except Exception as e:
        # 如果SDK方式失败，尝试使用HTTP请求方式（兼容某些API）
        try:
            headers = {
                "Content-Type": "application/json",
                "Authorization": f"Bearer {openai.api_key}"
            }
            data = {
                "model": embedding_model,
                "input": text
            }
            response = requests.post(f"{api_base}/embeddings", headers=headers, json=data)
            response.raise_for_status()
            resp_data = response.json()
            return np.array(resp_data['data'][0]['embedding']).astype('float32')
        except Exception as inner_e:
            print(f"Error getting embedding: {str(inner_e)}")
            # 使用简单的本地向量生成方法作为备用
            print("Using local fallback embedding generation")
            # 生成一个简单的向量，基于文本的字符统计和哈希
            import hashlib
            # 创建一个固定维度的向量 (128维)
            dim = 128
            vector = np.zeros(dim, dtype='float32')
            
            # 计算文本的哈希值作为种子
            text_hash = hashlib.md5(text.encode()).hexdigest()
            
            # 基于哈希值填充向量
            for i in range(min(len(text_hash), dim)):
                vector[i] = (ord(text_hash[i]) - ord('0')) / 16.0
            
            # 基于文本长度和字符分布添加一些变化
            for char in text[:100]:  # 只处理前100个字符
                idx = ord(char) % dim
                vector[idx] += 0.1
            
            # 归一化向量
            norm = np.linalg.norm(vector)
            if norm > 0:
                vector = vector / norm
            
            return vector

@app.post("/add-doc")
def add_doc(doc: DocInput):
    global vector_store
    vector = get_embedding(doc.text)
    
    # 确保向量存储已初始化
    if vector_store is None:
        # 使用默认配置初始化
        init_vector_store()
    
    # 添加向量
    success = vector_store.add_vector(vector, doc.doc_id, doc.text)
    if success:
        return {"status": "ok"}
    else:
        return {"status": "error", "message": "Failed to add document"}

@app.post("/search")
def search(q: QueryInput):
    global vector_store
    if vector_store is None:
        # 尝试初始化和加载向量存储
        init_vector_store()
        if vector_store is None:
            return {"results": []}
    
    try:
        # 尝试获取嵌入向量
        vector = get_embedding(q.text)
        # 尝试搜索向量
        try:
            results = vector_store.search_vectors(vector, q.top_k)
            return {"results": results}
        except Exception as e:
            # 如果搜索失败，可能是因为向量存储为空或其他问题
            print(f"Error searching vectors: {str(e)}")
            return {"results": []}
    except Exception as e:
        # 如果获取嵌入向量失败
        print(f"Error getting embedding: {str(e)}")
        return {"results": []}

# 初始化向量存储
def init_vector_store(store_config=None):
    global vector_store
    
    # 使用提供的配置或默认配置
    config = store_config or DEFAULT_STORE_CONFIG
    
    # 从环境变量获取配置
    store_type = os.getenv('VECTOR_STORE_TYPE', config['type'])
    
    # 根据存储类型创建向量存储
    try:
        if store_type == 'disk':
            # 从配置或环境变量获取磁盘存储参数
            index_path = os.getenv('VECTOR_STORE_DISK_INDEX_PATH', config['disk']['index_path'])
            ids_path = os.getenv('VECTOR_STORE_DISK_IDS_PATH', config['disk']['ids_path'])
            texts_path = os.getenv('VECTOR_STORE_DISK_TEXTS_PATH', config['disk']['texts_path'])
            
            # 确保数据目录存在
            os.makedirs(os.path.dirname(index_path), exist_ok=True)
            
            # 创建磁盘存储实例
            vector_store = create_vector_store(
                store_type='disk',
                index_path=index_path,
                ids_path=ids_path,
                texts_path=texts_path
            )
            
            # 尝试加载已有的索引
            vector_store.load()
        elif store_type == 'milvus':
            # 从配置或环境变量获取Milvus参数
            host = os.getenv('VECTOR_STORE_MILVUS_HOST', config['milvus']['host'])
            port = int(os.getenv('VECTOR_STORE_MILVUS_PORT', config['milvus']['port']))
            collection_name = os.getenv('VECTOR_STORE_MILVUS_COLLECTION', config['milvus']['collection_name'])
            
            # 创建Milvus存储实例
            vector_store = create_vector_store(
                store_type='milvus',
                host=host,
                port=port,
                collection_name=collection_name
            )
        else:
            # 默认使用内存存储
            vector_store = create_vector_store(store_type='memory')
            
        print(f"Initialized vector store: {store_type}")
        return True
    except Exception as e:
        print(f"Error initializing vector store: {str(e)}")
        vector_store = None
        return False

# 保存向量存储数据
def save_vector_store():
    global vector_store
    if vector_store is not None:
        print("Saving vector store data...")
        vector_store.save()
        print("Vector store data saved.")

# 添加服务器关闭时自动保存
atexit.register(save_vector_store)

# 添加根路由
def main():
    # 只有当直接运行脚本时才解析命令行参数
    parser = argparse.ArgumentParser(description='AI Vector Server')
    parser.add_argument('--env-path', type=str, default='../backend/.env', help='Path to .env file')
    parser.add_argument('--api-key', type=str, help='OpenAI API Key')
    parser.add_argument('--api-url', type=str, help='OpenAI API Base URL')
    parser.add_argument('--model', type=str, help='Embedding model name')
    parser.add_argument('--port', type=int, default=9000, help='Server port')
    parser.add_argument('--store-type', type=str, choices=['memory', 'disk', 'milvus'], help='Vector store type')
    parser.add_argument('--disk-index-path', type=str, help='Path to FAISS index file (for disk store)')
    parser.add_argument('--milvus-host', type=str, help='Milvus server host (for Milvus store)')
    parser.add_argument('--milvus-port', type=int, help='Milvus server port (for Milvus store)')
    args = parser.parse_args()
    
    # 如果提供了命令行参数，则覆盖默认配置
    if args.api_key:
        openai.api_key = args.api_key
    if args.api_url:
        openai.api_base = args.api_url
    if args.model:
        global embedding_model
        embedding_model = args.model
    
    # 重新加载.env文件（如果路径有变化）
    if args.env_path != '../backend/.env':
        env_path = Path(args.env_path)
        if env_path.exists():
            load_dotenv(dotenv_path=env_path)
    
    # 根据命令行参数覆盖环境变量
    if args.store_type:
        os.environ['VECTOR_STORE_TYPE'] = args.store_type
    if args.disk_index_path:
        os.environ['VECTOR_STORE_DISK_INDEX_PATH'] = args.disk_index_path
    if args.milvus_host:
        os.environ['VECTOR_STORE_MILVUS_HOST'] = args.milvus_host
    if args.milvus_port:
        os.environ['VECTOR_STORE_MILVUS_PORT'] = str(args.milvus_port)
    
    # 初始化向量存储
    init_vector_store()
    
    import uvicorn
    uvicorn.run(app, host='0.0.0.0', port=args.port)

if __name__ == "__main__":
    main()
