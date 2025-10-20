# 家政 AI 知识库管理系统

本系统包含 **三部分**：
1. **Python FAISS 向量检索服务**（AI 知识库检索，支持本地备用向量生成）
2. **Laravel 后端 API**（业务逻辑 + 数据管理）
3. **Vue3 前端**（用户 AI 问答界面 + 管理界面）

目的是：管理员维护家政知识库，用户提问时 AI 根据知识库回答，提供专业的家政服务咨询。

---

## **系统目录结构**
```
homestaff-ai/
├── ai_vector_server/   # Python 向量检索 + OpenAI Embedding
├── backend/            # Laravel 后端
└── frontend/           # Vue3 前端
```

---

## 一、部署环境要求

- Python >= 3.9 (用于 ai_vector_server)
- Node.js >= 16 (用于 Vue 前端)
- PHP >= 7.4, Composer (用于 Laravel)
- MySQL >= 5.7 (存储知识库数据和聊天日志)
- OpenAI API Key（可选，系统提供本地备用向量生成机制）

---

## 二、部署步骤

### 1. 部署 Python FAISS 向量服务

```bash
cd ai_vector_server
python3 -m venv venv
source venv/bin/activate   # Windows 用 venv\Scripts\activate
pip install -r requirements.txt
python server.py --port 9000
```

该服务提供两个 API：
- `POST /add-doc`：添加文档向量
- `POST /search`：搜索相似文档

---

### 2. 部署 Laravel 后端

```bash
cd backend
composer install
cp .env.example .env
```

**修改 .env 配置**
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homestaff
DB_USERNAME=你的数据库用户名
DB_PASSWORD=你的数据库密码

# AI配置
AI_API_KEY=sk-xxxxxx  # 你的 OpenAI Key（可选）
AI_API_URL=https://api.openai.com/v1

# 向量服务配置
EMBEDDING_API_URL=http://localhost:9000

# 系统模式和提示词（可自定义）
SYSTEM_MODE=家政服务
SYSTEM_PROMPT=你是一个专业的家政服务助手，负责为用户提供家政服务。

初始化数据库：
```bash
php artisan key:generate
php artisan migrate
```

初始化管理员用户：
```bash
php artisan user:init --name=admin --email=admin@qq.com --password=admin123
```

启动服务：
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

后端 API 地址示例：
- AI 问答：`POST /api/ai/ask`
- 优化并添加知识：`POST /api/ai/optimize-and-add`
- 知识列表：`GET /api/admin/knowledge`
- 添加知识：`POST /api/admin/knowledge`
- 更新知识：`POST /api/admin/knowledge/update`
- 删除知识：`POST /api/admin/knowledge/delete`
- 重新索引知识库：`POST /api/test/reindex-knowledge`（测试维护接口）

---

### 3. 部署 Vue 前端

```bash
cd frontend
npm install
npm run dev
```

默认会打开：`http://127.0.0.1:5173`

前端会通过 Vite 代理 `/api` 到 Laravel (`vite.config.js` 已配置)。

---

### 4. 系统使用说明

#### **管理员（知识库管理）**
1. 打开前端 → 点击 “管理知识库”
2. 填写标题、内容、标签、分类
3. 提交后，Laravel 调用 Python 向量服务做 embedding 存储，后续 AI 可检索到

#### **用户（AI 问答）**
1. 打开前端 → 点击 “AI 问答”
2. 输入问题 → 系统先在向量数据库查相关内容，附加到 Prompt 发送给 OpenAI
3. 返回答案 + 参考内容

---

### 5. 服务启动顺序建议
1. 启动 Python 向量服务（`ai_vector_server`）
2. 启动 Laravel 后端 API
3. 启动 Vue 前端

### 6. 知识库重新索引
当向量存储出现问题时，可以使用以下命令重新索引所有知识库内容：
```bash
curl -X POST http://localhost:8000/api/test/reindex-knowledge -H "Content-Type: application/json"
```

---

### 7. 常见问题
Q: OpenAI Key 错误导致无法生成向量或回答？
A: 系统已实现本地备用向量生成机制，即使无法连接外部 API，也能正常工作。可通过日志查看具体错误。

Q: 搜索不到知识？
A: 使用 `/api/test/reindex-knowledge` 接口重新索引所有知识库内容，确保向量存储正确初始化。

Q: Vue 请求 500 或 404？
A: 看 Laravel 后端日志 (`storage/logs/laravel.log`) 确认路由和参数是否正确。

Q: 如何修改默认管理员密码？
A: 登录系统后，点击右上角用户名旁边的"修改密码"按钮进行修改。

Q: 如何创建多个管理员账户？
A: 使用 `php artisan user:init` 命令并指定不同的邮箱和用户名。

Q: 系统模式和提示词在哪里配置？
A: 在后端的 `.env` 文件中，通过 `SYSTEM_MODE` 和 `SYSTEM_PROMPT` 环境变量进行配置。

---

## 作者提示
- 系统已增加本地备用向量生成机制，即使无法连接 OpenAI API 也能正常工作
- 增加了知识库重新索引测试接口，方便维护和故障排除
- Python FAISS 服务支持多种存储方式，推荐使用磁盘存储以确保数据持久化
- 部署到云服务器记得开放对应端口（8000, 9000, 5173）
- 可通过 `.env` 文件自定义系统模式和提示词，实现不同领域的专业问答

---

© 2025 家政 AI 知识库系统示例项目
