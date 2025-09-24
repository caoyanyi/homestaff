<template>
  <div class="ai-chat">
    <h2 class="chat-title">家政 AI 问答</h2>
    
    <div class="chat-history" ref="chatHistory">
      <div v-if="messages.length === 0" class="empty-state">
        <div class="empty-icon">💬</div>
        <p>开始与AI助手对话吧！</p>
      </div>
      
      <div 
        v-for="(message, index) in messages" 
        :key="index"
        :class="['message-wrapper', message.type === 'user' ? 'user-message' : 'ai-message']"
      >
        <div class="message-avatar">
          {{ message.type === 'user' ? '👤' : '🤖' }}
        </div>
        <div class="message-content">
          <div v-if="message.type === 'user'" class="user-question">
            {{ message.content }}
          </div>
          <div v-else class="ai-answer">
            <div class="answer-text" v-html="formatAnswer(message.content)"></div>
            
            <div v-if="message.context && message.context.length > 0" class="context-info">
              <div class="context-header">参考资料：</div>
              <div class="context-list">
                <div v-for="(item, idx) in message.context" :key="idx" class="context-item">
                  <span class="context-number">{{ idx + 1 }}.</span>
                  <span class="context-text">{{ item.text.substring(0, 100) }}...</span>
                </div>
              </div>
            </div>
          </div>
          <div class="message-time">{{ formatTime(message.timestamp) }}</div>
            
            <!-- 添加到知识库按钮 -->
            <div v-if="message.type === 'ai' && isLoggedIn && !message.addedToKnowledgeBase" class="message-actions">
              <button 
                @click="addToKnowledgeBase(message, index)" 
                :disabled="addingToKnowledgeBase"
                class="add-to-knowledge-btn"
              >
                {{ addingToKnowledgeBase ? '添加中...' : '添加到知识库' }}
              </button>
            </div>
            
            <!-- 已添加到知识库的标记 -->
            <div v-else-if="message.type === 'ai' && message.addedToKnowledgeBase" class="knowledge-added-tag">
              ✅ 已添加到知识库
            </div>
          </div>
        </div>
      
      <div v-if="loading" class="loading-message">
        <div class="loading-spinner"></div>
        <span>AI 正在思考...</span>
      </div>
    </div>
    
    <div class="chat-input-area">
      <textarea 
        v-model="question"
        @keydown.ctrl.enter="ask"
        @keydown.meta.enter="ask"
        placeholder="请输入您的问题...
(按住 Ctrl+Enter 快速发送)"
        rows="3"
        :disabled="loading"
      ></textarea>
      <button 
        @click="ask"
        :disabled="!question.trim() || loading"
        class="send-button"
      >
        {{ loading ? '发送中...' : '发送' }}
      </button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { marked } from 'marked';

export default {
  data() {
    return {
      question: '',
      messages: [],
      loading: false,
      // 聊天历史的本地存储键名
      chatHistoryKey: 'ai_chat_history',
      // 请求缓存的本地存储键名
      requestCacheKey: 'ai_request_cache',
      // 请求缓存对象
      requestCache: {},
      // 用户登录状态
      isLoggedIn: false,
      // 添加到知识库的加载状态
      addingToKnowledgeBase: false
    };
  },
  methods: {
    ask() {
      if (!this.question.trim() || this.loading) return;
      
      const userMessage = {
        type: 'user',
        content: this.question.trim(),
        timestamp: new Date()
      };
      
      this.messages.push(userMessage);
      const questionText = this.question;
      this.question = '';
      this.loading = true;
      
      // 滚动到底部
      this.$nextTick(() => {
        this.scrollToBottom();
      });
      
      // 保存聊天历史
      this.saveChatHistory();
      
      // 检查请求缓存
      const cachedResponse = this.getCachedResponse(questionText);
      if (cachedResponse) {
        // 使用缓存的响应
        setTimeout(() => {
          const aiMessage = {
            type: 'ai',
            content: cachedResponse.answer,
            context: cachedResponse.context || [],
            timestamp: new Date()
          };
          this.messages.push(aiMessage);
          this.loading = false;
          this.saveChatHistory();
          // 滚动到底部
          this.$nextTick(() => {
            this.scrollToBottom();
          });
        }, 100); // 短暂延迟以模拟真实请求
        return;
      }
      
      // 缓存未命中，发送API请求
      axios.post('/api/ai/ask', { question: questionText })
        .then(res => {
          const aiMessage = {
            type: 'ai',
            content: res.data.answer || '抱歉，我无法回答这个问题。',
            context: res.data.context || [],
            timestamp: new Date()
          };
          this.messages.push(aiMessage);
          
          // 缓存响应
          this.cacheResponse(questionText, {
            answer: res.data.answer,
            context: res.data.context
          });
        })
        .catch(error => {
          const errorMessage = {
            type: 'ai',
            content: `抱歉，发生错误：${error.message || '服务器错误'}`,
            timestamp: new Date()
          };
          this.messages.push(errorMessage);
        })
        .finally(() => {
          this.loading = false;
          // 保存聊天历史
          this.saveChatHistory();
          // 滚动到底部
          this.$nextTick(() => {
            this.scrollToBottom();
          });
        });
    },
    
    // 检查用户登录状态
    checkLoginStatus() {
      const token = localStorage.getItem('auth_token');
      if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        this.isLoggedIn = true;
      }
    },

    // 将问答对添加到知识库
    addToKnowledgeBase(message, index) {
      if (!this.isLoggedIn || this.addingToKnowledgeBase) return;

      this.addingToKnowledgeBase = true;

      // 查找对应的用户问题
      const userQuestionIndex = index - 1;
      if (userQuestionIndex < 0 || this.messages[userQuestionIndex].type !== 'user') {
        alert('找不到对应的用户问题');
        this.addingToKnowledgeBase = false;
        return;
      }

      const userQuestion = this.messages[userQuestionIndex].content;
      const aiAnswer = message.content;

      axios.post('/api/ai/optimize-and-add', {
        question: userQuestion,
        answer: aiAnswer
      })
        .then(res => {
          alert('成功添加到知识库！\n标题：' + res.data.optimized.title);
          // 可以在这里添加一个标记，表示该回答已添加到知识库
          this.$set(this.messages[index], 'addedToKnowledgeBase', true);
        })
        .catch(error => {
          console.error('添加到知识库失败:', error);
          alert('添加到知识库失败：' + (error.response?.data?.message || '未知错误'));
        })
        .finally(() => {
          this.addingToKnowledgeBase = false;
        });
    },

    // 加载聊天历史
    loadChatHistory() {
      try {
        const savedHistory = localStorage.getItem(this.chatHistoryKey);
        if (savedHistory) {
          this.messages = JSON.parse(savedHistory).map(msg => {
            // 恢复Date对象
            msg.timestamp = new Date(msg.timestamp);
            return msg;
          });
        }
      } catch (error) {
        console.error('加载聊天历史失败:', error);
      }
    },
    
    // 保存聊天历史
    saveChatHistory() {
      try {
        // 限制历史记录的最大条数，避免占用过多存储空间
        const MAX_HISTORY = 100;
        const historyToSave = this.messages.slice(-MAX_HISTORY);
        localStorage.setItem(this.chatHistoryKey, JSON.stringify(historyToSave));
      } catch (error) {
        console.error('保存聊天历史失败:', error);
      }
    },
    
    // 加载请求缓存
    loadRequestCache() {
      try {
        const savedCache = localStorage.getItem(this.requestCacheKey);
        if (savedCache) {
          this.requestCache = JSON.parse(savedCache);
        }
      } catch (error) {
        console.error('加载请求缓存失败:', error);
      }
    },
    
    // 保存请求缓存
    saveRequestCache() {
      try {
        // 限制缓存大小，避免占用过多存储空间
        const MAX_CACHE_SIZE = 50;
        const cacheKeys = Object.keys(this.requestCache);
        if (cacheKeys.length > MAX_CACHE_SIZE) {
          // 删除最早的缓存项
          const keysToDelete = cacheKeys.slice(0, cacheKeys.length - MAX_CACHE_SIZE);
          keysToDelete.forEach(key => {
            delete this.requestCache[key];
          });
        }
        localStorage.setItem(this.requestCacheKey, JSON.stringify(this.requestCache));
      } catch (error) {
        console.error('保存请求缓存失败:', error);
      }
    },
    
    // 获取缓存的响应
    getCachedResponse(question) {
      // 使用简单的文本相似性检查
      const normalizedQuestion = this.normalizeText(question);
      
      for (const [cachedQuestion, response] of Object.entries(this.requestCache)) {
        const normalizedCachedQuestion = this.normalizeText(cachedQuestion);
        
        // 如果问题相似度高（这里使用简单的包含关系），返回缓存的响应
        if (normalizedQuestion.includes(normalizedCachedQuestion) || 
            normalizedCachedQuestion.includes(normalizedQuestion)) {
          return response;
        }
      }
      
      return null;
    },
    
    // 缓存响应
    cacheResponse(question, response) {
      this.requestCache[question] = response;
      this.saveRequestCache();
    },
    
    // 文本规范化（用于缓存匹配）
    normalizeText(text) {
      return text
        .toLowerCase()
        .trim()
        .replace(/\s+/g, ' ') // 合并多余空格
        .replace(/[,.?!，。？！]/g, ''); // 移除标点符号
    },
    scrollToBottom() {
      const chatHistory = this.$refs.chatHistory;
      if (chatHistory) {
        chatHistory.scrollTop = chatHistory.scrollHeight;
      }
    },
    formatTime(date) {
      const d = new Date(date);
      const hours = d.getHours().toString().padStart(2, '0');
      const minutes = d.getMinutes().toString().padStart(2, '0');
      return `${hours}:${minutes}`;
    },
    formatAnswer(text) {
      // 解析Markdown格式内容为HTML
      return marked.parse(text);
    }
  },
  mounted() {
    // 加载聊天历史和请求缓存
    this.loadChatHistory();
    this.loadRequestCache();
    
    // 检查用户登录状态
    this.checkLoginStatus();
    
    // 监听窗口大小变化，调整聊天区域高度
    window.addEventListener('resize', this.scrollToBottom);
    
    // 滚动到底部
    this.$nextTick(() => {
      this.scrollToBottom();
    });
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.scrollToBottom);
    // 确保在组件卸载前保存聊天历史
    this.saveChatHistory();
  }
};
</script>

<style scoped>
.ai-chat {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.chat-title {
  text-align: center;
  color: #333;
  font-size: 1.5rem;
  margin-bottom: 20px;
  font-weight: 600;
}

.chat-history {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 20px;
  scroll-behavior: smooth;
}

.chat-history::-webkit-scrollbar {
  width: 6px;
}

.chat-history::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.chat-history::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.chat-history::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 15px;
}

.message-wrapper {
  display: flex;
  margin-bottom: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.user-message {
  justify-content: flex-end;
}

.ai-message {
  justify-content: flex-start;
}

.message-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e1e5e9;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
  margin: 0 10px;
}

.user-message .message-avatar {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.message-content {
  max-width: 70%;
}

.user-question {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 15px 20px;
  border-radius: 18px 18px 4px 18px;
  font-size: 15px;
  line-height: 1.5;
}

.ai-answer {
  background: white;
  padding: 15px 20px;
  border-radius: 18px 18px 18px 4px;
  border: 1px solid #e1e5e9;
  font-size: 15px;
  line-height: 1.6;
  color: #333;
}

.answer-text {
      margin-bottom: 10px;
    }
    
    /* Markdown样式 */
    .answer-text h1, .answer-text h2, .answer-text h3, 
    .answer-text h4, .answer-text h5, .answer-text h6 {
      margin-top: 20px;
      margin-bottom: 10px;
      color: #333;
      font-weight: 600;
    }
    
    .answer-text h1 { font-size: 1.8rem; }
    .answer-text h2 { font-size: 1.5rem; }
    .answer-text h3 { font-size: 1.3rem; }
    .answer-text h4 { font-size: 1.1rem; }
    .answer-text h5 { font-size: 1rem; }
    .answer-text h6 { font-size: 0.9rem; color: #666; }
    
    .answer-text p {
      margin-bottom: 12px;
      line-height: 1.6;
    }
    
    .answer-text ul, .answer-text ol {
      margin-bottom: 12px;
      padding-left: 25px;
    }
    
    .answer-text li {
      margin-bottom: 6px;
      line-height: 1.5;
    }
    
    .answer-text ul li:before {
      content: "•";
      margin-right: 8px;
      color: #667eea;
    }
    
    .answer-text strong {
      font-weight: 600;
      color: #333;
    }
    
    .answer-text em {
      font-style: italic;
      color: #666;
    }
    
    .answer-text code {
      background: #f5f5f5;
      padding: 2px 5px;
      border-radius: 3px;
      font-family: 'Courier New', Courier, monospace;
      font-size: 0.9em;
      color: #e74c3c;
    }
    
    .answer-text pre {
      background: #282c34;
      color: #abb2bf;
      padding: 15px;
      border-radius: 6px;
      overflow-x: auto;
      margin-bottom: 12px;
      font-family: 'Courier New', Courier, monospace;
    }
    
    .answer-text pre code {
      background: transparent;
      color: inherit;
      padding: 0;
      border-radius: 0;
    }
    
    .answer-text blockquote {
      border-left: 4px solid #667eea;
      padding-left: 15px;
      color: #666;
      margin-bottom: 12px;
      font-style: italic;
    }
    
    .answer-text a {
      color: #667eea;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    
    .answer-text a:hover {
      color: #5a67d8;
      text-decoration: underline;
    }

.context-info {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #eee;
}

.context-header {
  font-size: 12px;
  color: #666;
  margin-bottom: 8px;
  font-weight: 500;
}

.context-list {
  max-height: 150px;
  overflow-y: auto;
}

.context-item {
  font-size: 12px;
  color: #666;
  margin-bottom: 5px;
  padding: 5px 10px;
  background: #f5f5f5;
  border-radius: 4px;
  display: flex;
  gap: 8px;
}

.context-number {
  font-weight: bold;
  color: #667eea;
  min-width: 20px;
}

.context-text {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.message-time {
  font-size: 11px;
  color: #999;
  margin-top: 5px;
  text-align: right;
}

.user-message .message-time {
  text-align: left;
}

.loading-message {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: #666;
}

.loading-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-right: 10px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.chat-input-area {
  display: flex;
  gap: 10px;
  align-items: flex-end;
}

.chat-input-area textarea {
  flex: 1;
  padding: 12px 15px;
  border: 2px solid #e1e5e9;
  border-radius: 8px;
  font-size: 15px;
  resize: none;
  font-family: inherit;
  transition: border-color 0.3s ease;
  box-sizing: border-box;
}

.chat-input-area textarea:focus {
  outline: none;
  border-color: #667eea;
}

.chat-input-area textarea:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}

.send-button {
  padding: 12px 24px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  height: fit-content;
}

.send-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.send-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

    .message-actions {
      margin-top: 10px;
    }

    .add-to-knowledge-btn {
      background: #4CAF50;
      color: white;
      border: none;
      border-radius: 4px;
      padding: 4px 12px;
      font-size: 12px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .add-to-knowledge-btn:hover:not(:disabled) {
      background: #45a049;
    }

    .add-to-knowledge-btn:disabled {
      background: #cccccc;
      cursor: not-allowed;
    }

    .knowledge-added-tag {
      margin-top: 8px;
      font-size: 11px;
      color: #4CAF50;
      font-weight: 500;
    }

    @media (max-width: 768px) {
      .message-content {
        max-width: 85%;
      }
      
      .user-question,
      .ai-answer {
        padding: 12px 15px;
        font-size: 14px;
      }
      
      .chat-input-area {
        flex-direction: column;
      }
      
      .send-button {
        width: 100%;
        padding: 12px;
      }
    }
</style>
