<template>
  <div class="login-container">
    <div class="login-form">
      <h2>欢迎登录家政AI知识库</h2>
      <div class="form-group">
        <label for="email">邮箱</label>
        <input 
          type="email" 
          id="email" 
          v-model="email" 
          placeholder="请输入您的邮箱"
          required
          :class="{ 'error': errors.email }"
        >
        <div v-if="errors.email" class="error-message">{{ errors.email }}</div>
      </div>
      <div class="form-group">
        <label for="password">密码</label>
        <input 
          type="password" 
          id="password" 
          v-model="password" 
          placeholder="请输入您的密码"
          required
          :class="{ 'error': errors.password }"
        >
        <div v-if="errors.password" class="error-message">{{ errors.password }}</div>
      </div>
      <button 
        class="login-button" 
        @click="handleLogin" 
        :disabled="loading"
      >
        {{ loading ? '登录中...' : '登录' }}
      </button>
      <button 
        class="cancel-button" 
        @click="handleCancel" 
        :disabled="loading"
      >
        取消
      </button>
      <div v-if="error" class="form-error">{{ error }}</div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      email: '',
      password: '',
      loading: false,
      error: '',
      errors: {}
    };
  },
  methods: {
    handleLogin() {
      this.loading = true;
      this.error = '';
      this.errors = {};
      
      axios.post('/api/login', {
        email: this.email,
        password: this.password
      })
      .then(response => {
        // 存储登录状态到 localStorage
        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        // 设置 axios 默认 headers
        axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
        
        // 触发登录成功事件
        this.$emit('login-success');
      })
      .catch(error => {
        this.loading = false;
        if (error.response?.status === 422) {
          // 表单验证错误
          this.errors = error.response.data.errors;
        } else {
          // 其他错误
          this.error = error.response?.data?.message || '登录失败，请重试';
        }
      });
    },
    handleCancel() {
      this.$emit('cancel');
    }
  }
};
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
}

.login-form {
  background: white;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 400px;
}

.login-form h2 {
  margin-bottom: 30px;
  text-align: center;
  color: #333;
  font-size: 1.8rem;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
}

.form-group input {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid #e1e5e9;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 0.3s ease;
  box-sizing: border-box;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
}

.form-group input.error {
  border-color: #e74c3c;
}

.error-message {
  color: #e74c3c;
  font-size: 14px;
  margin-top: 5px;
}

.login-form .button-group {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

.login-button,
.cancel-button {
  flex: 1;
  padding: 14px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.login-button {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.cancel-button {
  background: #f5f5f5;
  color: #555;
  border: 2px solid #e1e5e9;
}

.login-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
  }

  .cancel-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    border-color: #667eea;
  }

  .login-button:disabled,
  .cancel-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }

.form-error {
  color: #e74c3c;
  text-align: center;
  margin-top: 15px;
  font-size: 14px;
}

@media (max-width: 480px) {
  .login-form {
    padding: 30px 20px;
  }
  
  .login-form h2 {
    font-size: 1.5rem;
  }
}
</style>