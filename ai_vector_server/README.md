# AI Vector Server

基于FastAPI和FAISS的向量搜索引擎，支持多种向量存储后端，包括内存存储、磁盘持久化存储和Milvus向量数据库。

## 特性

- 支持多种向量存储后端：
  - 内存存储（默认，重启会丢失数据）
  - 磁盘持久化存储（数据保存在磁盘，重启后不丢失）
  - Milvus向量数据库（专业的向量数据库，支持大规模数据存储和高并发查询）
- 兼容OpenAI API格式的文本嵌入接口
- 支持通过环境变量或命令行参数灵活配置
- 自动保存数据（磁盘存储和Milvus模式下）

## 快速开始

### 安装依赖

```bash
pip install -r requirements.txt

# 如果需要使用Milvus存储，额外安装Milvus客户端
# pip install pymilvus>=2.3.0
```

### 运行服务

```bash
python server.py
```

默认端口为9000，可以通过`--port`参数修改。

## 向量存储配置

### 1. 通过环境变量配置

可以在`.env`文件中设置以下环境变量（默认读取`../backend/.env`）：

```
# 向量存储类型：memory（内存存储）、disk（磁盘存储）、milvus（Milvus向量数据库）
VECTOR_STORE_TYPE=disk

# 磁盘存储配置（仅在VECTOR_STORE_TYPE=disk时生效）
VECTOR_STORE_DISK_INDEX_PATH=data/faiss_index.index
VECTOR_STORE_DISK_IDS_PATH=data/doc_ids.npy
VECTOR_STORE_DISK_TEXTS_PATH=data/doc_texts.txt

# Milvus配置（仅在VECTOR_STORE_TYPE=milvus时生效）
VECTOR_STORE_MILVUS_HOST=localhost
VECTOR_STORE_MILVUS_PORT=19530
VECTOR_STORE_MILVUS_COLLECTION=vectors

# OpenAI API配置
AI_API_KEY=sk-xxxxx
AI_API_URL=https://api.openai.com/v1
AI_MODEL=text-embedding-ada-002
```

### 2. 通过命令行参数配置

```bash
# 使用磁盘存储
python server.py --store-type disk

# 自定义磁盘存储路径
python server.py --store-type disk --disk-index-path custom/path/faiss_index.index

# 使用Milvus存储
python server.py --store-type milvus --milvus-host localhost --milvus-port 19530

# 修改端口
python server.py --port 9001

# 指定.env文件路径
python server.py --env-path /path/to/.env
```

## API接口

### 添加文档

```bash
POST /add-doc
Content-Type: application/json

{
  "doc_id": 1,
  "text": "这是一段示例文本"
}
```

### 搜索文档

```bash
POST /search
Content-Type: application/json

{
  "text": "查询文本",
  "top_k": 5
}
```

## 存储类型选择指南

| 存储类型 | 优点 | 缺点 | 适用场景 |
|--------|------|------|--------|
| 内存存储 | 速度快，无需额外配置 | 重启后数据丢失 | 开发测试、临时数据 |
| 磁盘存储 | 数据持久化，无需额外服务 | 大规模数据性能有限 | 中小规模数据，单机部署 |
| Milvus | 专业向量数据库，支持大规模数据和高并发 | 需要额外部署Milvus服务 | 生产环境，大规模数据 |

## 迁移指南

如果您之前使用的是内存存储，并希望迁移到磁盘存储或Milvus：

1. 先使用内存存储启动服务，确保所有数据已添加到向量库
2. 修改配置，切换到目标存储类型（磁盘存储或Milvus）
3. 调用`/search`接口或重启服务，系统会自动创建新的存储结构
4. 对于磁盘存储，可以备份`data/`目录以确保数据安全

## 开发说明

- 代码采用了抽象工厂模式，便于扩展新的向量存储后端
- 向量存储接口定义在`vector_store.py`中的`VectorStore`抽象基类中
- 服务器关闭时会自动保存数据（通过`atexit`注册钩子）
- Milvus存储采用了懒加载方式，只在需要时才导入相关库

## 注意事项

- 使用磁盘存储时，请确保数据目录有写权限
- 使用Milvus存储时，需要提前部署Milvus服务
- 向量维度由第一个添加的向量决定，请确保所有向量使用相同的嵌入模型
- 服务重启时，磁盘存储会自动加载之前保存的数据