import abc
import os
import numpy as np
import faiss
from pathlib import Path

# 向量存储抽象基类
class VectorStore(abc.ABC):
    @abc.abstractmethod
    def add_vector(self, vector, doc_id, text):
        pass
    
    @abc.abstractmethod
    def search_vectors(self, query_vector, top_k):
        pass
    
    @abc.abstractmethod
    def save(self):
        pass
    
    @abc.abstractmethod
    def load(self):
        pass

# FAISS内存存储实现
class FAISSMemoryStore(VectorStore):
    def __init__(self):
        self.index = None
        self.vector_dimension = None
        self.doc_ids = []
        self.doc_texts = []
    
    def add_vector(self, vector, doc_id, text):
        # 动态初始化索引
        if self.index is None:
            self.vector_dimension = len(vector)
            self.index = faiss.IndexFlatL2(self.vector_dimension)
            print(f"Initialized FAISS memory index with dimension: {self.vector_dimension}")
        
        self.index.add(np.array([vector]))
        self.doc_ids.append(doc_id)
        self.doc_texts.append(text)
        return True
    
    def search_vectors(self, query_vector, top_k):
        if self.index is None:
            return []
        
        # 确保查询向量维度与索引一致
        if len(query_vector) != self.vector_dimension:
            print(f"Warning: Query vector dimension ({len(query_vector)}) does not match index dimension ({self.vector_dimension})")
        
        D, I = self.index.search(np.array([query_vector]), top_k)
        results = []
        for idx in I[0]:
            if idx < len(self.doc_ids):
                results.append({"doc_id": self.doc_ids[idx], "text": self.doc_texts[idx]})
        return results
    
    def save(self):
        # 内存存储不支持保存
        return False
    
    def load(self):
        # 内存存储不支持加载
        return False

# FAISS磁盘存储实现
class FAISSDiskStore(VectorStore):
    def __init__(self, index_path, ids_path, texts_path):
        self.index_path = index_path
        self.ids_path = ids_path
        self.texts_path = texts_path
        self.index = None
        self.vector_dimension = None
        self.doc_ids = []
        self.doc_texts = []
    
    def add_vector(self, vector, doc_id, text):
        # 动态初始化索引
        if self.index is None:
            self.vector_dimension = len(vector)
            self.index = faiss.IndexFlatL2(self.vector_dimension)
            print(f"Initialized FAISS disk index with dimension: {self.vector_dimension}")
        
        self.index.add(np.array([vector]))
        self.doc_ids.append(doc_id)
        self.doc_texts.append(text)
        return True
    
    def search_vectors(self, query_vector, top_k):
        if self.index is None:
            # 尝试加载索引
            self.load()
            if self.index is None:
                return []
        
        # 确保查询向量维度与索引一致
        if len(query_vector) != self.vector_dimension:
            print(f"Warning: Query vector dimension ({len(query_vector)}) does not match index dimension ({self.vector_dimension})")
        
        D, I = self.index.search(np.array([query_vector]), top_k)
        results = []
        for idx in I[0]:
            if idx < len(self.doc_ids):
                results.append({"doc_id": self.doc_ids[idx], "text": self.doc_texts[idx]})
        return results
    
    def save(self):
        try:
            # 创建目录（如果不存在）
            os.makedirs(os.path.dirname(self.index_path), exist_ok=True)
            
            # 保存FAISS索引
            faiss.write_index(self.index, self.index_path)
            
            # 保存文档ID和文本
            np.save(self.ids_path, np.array(self.doc_ids))
            
            # 保存文本内容
            with open(self.texts_path, 'w', encoding='utf-8') as f:
                for text in self.doc_texts:
                    # 使用base64编码来处理换行符等特殊字符
                    import base64
                    encoded = base64.b64encode(text.encode('utf-8')).decode('utf-8')
                    f.write(encoded + '\n')
            
            print(f"FAISS index saved to {self.index_path}")
            print(f"Doc IDs saved to {self.ids_path}")
            print(f"Doc texts saved to {self.texts_path}")
            return True
        except Exception as e:
            print(f"Error saving FAISS index: {str(e)}")
            return False
    
    def load(self):
        try:
            # 检查文件是否存在
            if not os.path.exists(self.index_path) or not os.path.exists(self.ids_path) or not os.path.exists(self.texts_path):
                print(f"Index files not found at {self.index_path}")
                return False
            
            # 加载FAISS索引
            self.index = faiss.read_index(self.index_path)
            self.vector_dimension = self.index.d
            
            # 加载文档ID
            self.doc_ids = np.load(self.ids_path).tolist()
            
            # 加载文本内容
            self.doc_texts = []
            with open(self.texts_path, 'r', encoding='utf-8') as f:
                for line in f:
                    # 解码base64字符串
                    import base64
                    text = base64.b64decode(line.strip()).decode('utf-8')
                    self.doc_texts.append(text)
            
            print(f"FAISS index loaded from {self.index_path}")
            print(f"Loaded {len(self.doc_ids)} documents")
            return True
        except Exception as e:
            print(f"Error loading FAISS index: {str(e)}")
            return False

# Milvus存储实现（可选）
class MilvusStore(VectorStore):
    def __init__(self, **kwargs):
        try:
            from pymilvus import connections, Collection, CollectionSchema, FieldSchema, DataType
            self.pymilvus_available = True
            self.connections = connections
            self.Collection = Collection
            self.CollectionSchema = CollectionSchema
            self.FieldSchema = FieldSchema
            self.DataType = DataType
            
            # Milvus配置
            self.host = kwargs.get('host', 'localhost')
            self.port = kwargs.get('port', 19530)
            self.collection_name = kwargs.get('collection_name', 'vectors')
            self.collection = None
            
            # 连接Milvus
            self._connect()
            # 创建集合（如果不存在）
            self._create_collection_if_not_exists()
        except ImportError:
            print("pymilvus library not found. Please install it with 'pip install pymilvus'")
            self.pymilvus_available = False
    
    def _connect(self):
        if self.pymilvus_available:
            self.connections.connect("default", host=self.host, port=self.port)
            print(f"Connected to Milvus at {self.host}:{self.port}")
    
    def _create_collection_if_not_exists(self):
        if not self.pymilvus_available:
            return
        
        try:
            # 定义字段
            fields = [
                self.FieldSchema(name="id", dtype=self.DataType.INT64, is_primary=True, auto_id=False),
                self.FieldSchema(name="vector", dtype=self.DataType.FLOAT_VECTOR, dim=768),  # 默认使用768维
                self.FieldSchema(name="text", dtype=self.DataType.VARCHAR, max_length=65535)
            ]
            
            # 创建schema
            schema = self.CollectionSchema(fields, "Vector search collection")
            
            # 创建集合（如果不存在）
            if self.collection_name not in self.connections.get_connection_addr("default").list_collections():
                self.collection = self.Collection(self.collection_name, schema)
                print(f"Created Milvus collection: {self.collection_name}")
            else:
                self.collection = self.Collection(self.collection_name)
                print(f"Loaded Milvus collection: {self.collection_name}")
        except Exception as e:
            print(f"Error creating Milvus collection: {str(e)}")
    
    def add_vector(self, vector, doc_id, text):
        if not self.pymilvus_available or self.collection is None:
            return False
        
        try:
            # 准备数据
            entities = [
                [doc_id],  # id
                [vector.tolist()],  # vector
                [text[:65535]]  # text (截断超长文本)
            ]
            
            # 插入数据
            self.collection.insert(entities)
            # 创建索引（如果不存在）
            if not self.collection.has_index('vector'):
                index_params = {"index_type": "IVF_FLAT", "metric_type": "L2", "params": {"nlist": 128}}
                self.collection.create_index("vector", index_params)
                self.collection.load()
            return True
        except Exception as e:
            print(f"Error adding vector to Milvus: {str(e)}")
            return False
    
    def search_vectors(self, query_vector, top_k):
        if not self.pymilvus_available or self.collection is None:
            return []
        
        try:
            # 确保集合已加载
            if not self.collection.is_loaded:
                self.collection.load()
            
            # 搜索参数
            search_params = {"metric_type": "L2", "params": {"nprobe": 10}}
            
            # 执行搜索
            results = self.collection.search(
                data=[query_vector.tolist()],
                anns_field="vector",
                param=search_params,
                limit=top_k,
                expr=None,
                output_fields=["id", "text"]
            )
            
            # 处理搜索结果
            search_results = []
            for hits in results:
                for hit in hits:
                    search_results.append({
                        "doc_id": hit.entity.get("id"),
                        "text": hit.entity.get("text")
                    })
            return search_results
        except Exception as e:
            print(f"Error searching vectors in Milvus: {str(e)}")
            return []
    
    def save(self):
        if not self.pymilvus_available:
            return False
        
        try:
            # Milvus数据自动持久化
            print("Milvus data is automatically persisted")
            return True
        except Exception as e:
            print(f"Error saving Milvus data: {str(e)}")
            return False
    
    def load(self):
        if not self.pymilvus_available:
            return False
        
        try:
            # Milvus数据自动加载
            if self.collection is not None and not self.collection.is_loaded:
                self.collection.load()
            print("Milvus data is automatically loaded")
            return True
        except Exception as e:
            print(f"Error loading Milvus data: {str(e)}")
            return False

# 工厂方法创建向量存储实例
def create_vector_store(store_type='memory', **kwargs):
    if store_type == 'memory':
        return FAISSMemoryStore()
    elif store_type == 'disk':
        index_path = kwargs.get('index_path', 'data/faiss_index.index')
        ids_path = kwargs.get('ids_path', 'data/doc_ids.npy')
        texts_path = kwargs.get('texts_path', 'data/doc_texts.txt')
        return FAISSDiskStore(index_path, ids_path, texts_path)
    elif store_type == 'milvus':
        return MilvusStore(**kwargs)
    else:
        raise ValueError(f"Unsupported store type: {store_type}")