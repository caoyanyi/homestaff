# 家政AI知识库管理系统 - 后端

Laravel后端服务，负责处理业务逻辑、数据管理以及与向量服务器交互。

## 核心功能

- **AI问答接口**：接收用户问题，调用向量服务器搜索相关知识，生成回答
- **知识库管理**：提供知识的增删改查功能
- **用户认证**：基于Laravel Sanctum的用户认证系统
- **测试维护接口**：提供知识库重新索引等功能

## 环境要求

- PHP >= 7.4
- Composer
- MySQL >= 5.7

## 部署步骤

### 1. 安装依赖

```bash
composer install
```

### 2. 配置环境变量

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置数据库连接、API密钥等信息：

```env
# 数据库配置
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homestaff
DB_USERNAME=root
DB_PASSWORD=

# AI配置（可选）
AI_API_KEY=sk-xxxxx
AI_API_URL=https://api.openai.com/v1

# 向量服务配置
EMBEDDING_API_URL=http://localhost:9000

# 系统模式和提示词（可自定义）
SYSTEM_MODE=家政服务
SYSTEM_PROMPT=你是一个专业的家政服务助手，负责为用户提供家政服务。
```

### 3. 初始化应用

```bash
# 生成应用密钥
php artisan key:generate

# 运行数据库迁移
php artisan migrate

# 初始化管理员用户
php artisan user:init --name=admin --email=admin@qq.com --password=admin123
```

### 4. 启动服务

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## API接口列表

### 公共接口

- **AI问答**：`POST /api/ai/ask`
  - 参数：`{"question": "用户问题"}`
  - 返回：AI回答及使用的知识库内容

### 需要认证的接口

- **优化并添加知识**：`POST /api/ai/optimize-and-add`
- **获取知识列表**：`GET /api/admin/knowledge`
- **添加知识**：`POST /api/admin/knowledge`
- **更新知识**：`POST /api/admin/knowledge/update`
- **删除知识**：`POST /api/admin/knowledge/delete`
- **用户登录**：`POST /api/auth/login`
- **用户登出**：`POST /api/auth/logout`

### 测试维护接口

- **重新索引知识库**：`POST /api/test/reindex-knowledge`
  - 将所有知识库内容重新添加到向量存储
  - 返回总数、成功数和失败数统计

## 项目结构

```
backend/
├── app/
│   ├── Http/Controllers/      # 控制器
│   │   ├── AIController.php   # AI相关接口
│   │   └── AdminKnowledgeController.php # 知识库管理
│   └── Models/                # 数据模型
│       └── Knowledge.php      # 知识库模型
├── routes/
│   └── api.php                # API路由定义
└── config/                    # 配置文件
```

## 常见问题排查

### 1. 向量服务器连接失败

- 检查 `EMBEDDING_API_URL` 配置是否正确
- 确认向量服务器是否在运行：`curl http://localhost:9000/health`
- 使用测试接口重新索引：`curl -X POST http://localhost:8000/api/test/reindex-knowledge`

### 2. 数据库连接问题

- 检查 `.env` 中的数据库配置
- 确认MySQL服务正在运行

### 3. 日志查看

```bash
# 查看Laravel日志
cat storage/logs/laravel.log
```

## 许可证

MIT License
