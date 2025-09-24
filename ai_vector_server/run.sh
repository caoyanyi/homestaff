#!/bin/bash

# 创建虚拟环境（如果不存在）
if [ ! -d "venv" ]; then
    python3 -m venv venv
fi

source venv/bin/activate

# 安装依赖
pip install -r requirements.txt

# 检查是否有自定义.env文件
if [ -f ".env" ]; then
    echo "Using .env file from current directory"
else
    echo "No .env file found in current directory, will use default settings or .env from parent directory"
fi

# 启动服务
python server.py --port 9000
