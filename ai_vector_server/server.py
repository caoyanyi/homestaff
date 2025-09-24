import sys
import os
import argparse
from dotenv import load_dotenv
from fastapi import FastAPI
from pydantic import BaseModel
import faiss
import numpy as np
import openai
import requests
from pathlib import Path

# 加载.env文件
env_path = Path('../backend/.env')
if env_path.exists():
    load_dotenv(dotenv_path=env_path)

# 配置OpenAI客户端（默认配置，可通过参数或环境变量覆盖）
openai.api_key = os.getenv('AI_API_KEY', 'sk-xxxxx')
api_base = os.getenv('AI_API_URL', 'https://api.openai.com/v1')
if api_base != 'https://api.openai.com/v1':
    openai.api_base = api_base

# 获取模型配置
embedding_model = os.getenv('AI_MODEL', 'text-embedding-ada-002')

app = FastAPI()

# 不预设固定维度，而是根据第一个向量的维度动态初始化
index = None
vector_dimension = None
doc_ids = []
doc_texts = []

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
            raise

@app.post("/add-doc")
def add_doc(doc: DocInput):
    global index, vector_dimension
    vector = get_embedding(doc.text)
    
    # 动态初始化索引
    if index is None:
        vector_dimension = len(vector)
        index = faiss.IndexFlatL2(vector_dimension)
        print(f"Initialized FAISS index with dimension: {vector_dimension}")
    
    index.add(np.array([vector]))
    doc_ids.append(doc.doc_id)
    doc_texts.append(doc.text)
    return {"status": "ok"}

@app.post("/search")
def search(q: QueryInput):
    if index is None:
        return {"results": []}
    
    vector = get_embedding(q.text)
    # 确保查询向量维度与索引一致
    if len(vector) != vector_dimension:
        print(f"Warning: Query vector dimension ({len(vector)}) does not match index dimension ({vector_dimension})")
        
    D, I = index.search(np.array([vector]), q.top_k)
    results = []
    for idx in I[0]:
        if idx < len(doc_ids):
            results.append({"doc_id": doc_ids[idx], "text": doc_texts[idx]})
    return {"results": results}

# 添加根路由
def main():
    # 只有当直接运行脚本时才解析命令行参数
    parser = argparse.ArgumentParser(description='AI Vector Server')
    parser.add_argument('--env-path', type=str, default='../backend/.env', help='Path to .env file')
    parser.add_argument('--api-key', type=str, help='OpenAI API Key')
    parser.add_argument('--api-url', type=str, help='OpenAI API Base URL')
    parser.add_argument('--model', type=str, help='Embedding model name')
    parser.add_argument('--port', type=int, default=9000, help='Server port')
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
    
    import uvicorn
    uvicorn.run(app, host='0.0.0.0', port=args.port)

if __name__ == "__main__":
    main()
