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

export default {
  data() {
    return {
      question: '',
      messages: [],
      loading: false
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
      
      axios.post('/api/ai/ask', { question: questionText })
        .then(res => {
          const aiMessage = {
            type: 'ai',
            content: res.data.answer || '抱歉，我无法回答这个问题。',
            context: res.data.context || [],
            timestamp: new Date()
          };
          this.messages.push(aiMessage);
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
          // 滚动到底部
          this.$nextTick(() => {
            this.scrollToBottom();
          });
        });
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
      // 将换行符转换为 <br> 标签
      return text.replace(/\n/g, '<br>');
    }
  },
  mounted() {
    // 监听窗口大小变化，调整聊天区域高度
    window.addEventListener('resize', this.scrollToBottom);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.scrollToBottom);
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
