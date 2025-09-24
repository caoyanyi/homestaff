<template>
  <div class="change-password-container">
    <div class="change-password-form">
      <h2>修改密码</h2>
      
      <div class="form-group">
        <label for="current_password">当前密码</label>
        <input 
          type="password" 
          id="current_password" 
          v-model="current_password" 
          placeholder="请输入当前密码"
          required
          :class="{ 'error': errors.current_password }"
        >
        <div v-if="errors.current_password" class="error-message">{{ errors.current_password }}</div>
      </div>
      
      <div class="form-group">
        <label for="new_password">新密码</label>
        <input 
          type="password" 
          id="new_password" 
          v-model="new_password" 
          placeholder="请输入新密码（至少8位）"
          required
          :class="{ 'error': errors.new_password }"
        >
        <div v-if="errors.new_password" class="error-message">{{ errors.new_password }}</div>
      </div>
      
      <div class="form-group">
        <label for="new_password_confirmation">确认新密码</label>
        <input 
          type="password" 
          id="new_password_confirmation" 
          v-model="new_password_confirmation" 
          placeholder="请再次输入新密码"
          required
        >
      </div>
      
      <button 
        class="submit-button" 
        @click="handleSubmit" 
        :disabled="loading"
      >
        {{ loading ? '修改中...' : '确认修改' }}
      </button>
      
      <button 
        class="cancel-button" 
        @click="handleCancel"
        :disabled="loading"
      >
        取消
      </button>
      
      <div v-if="error" class="form-error">{{ error }}</div>
      <div v-if="success" class="form-success">{{ success }}</div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
      loading: false,
      error: '',
      success: '',
      errors: {}
    };
  },
  methods: {
    handleSubmit() {
      this.loading = true;
      this.error = '';
      this.success = '';
      this.errors = {};
      
      axios.post('/api/user/password', {
        current_password: this.current_password,
        new_password: this.new_password,
        new_password_confirmation: this.new_password_confirmation
      })
      .then(response => {
        this.loading = false;
        this.success = response.data.message;
        
        // 清空表单
        this.current_password = '';
        this.new_password = '';
        this.new_password_confirmation = '';
        
        // 3秒后自动返回上一页或退出登录
        setTimeout(() => {
          this.$emit('password-updated');
        }, 3000);
      })
      .catch(error => {
        this.loading = false;
        if (error.response?.status === 422) {
          // 表单验证错误
          this.errors = error.response.data.errors;
        } else {
          // 其他错误
          this.error = error.response?.data?.message || '密码修改失败，请重试';
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
.change-password-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
}

.change-password-form {
  background: white;
  padding: 40px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 500px;
}

.change-password-form h2 {
  margin: 0 0 30px 0;
  text-align: center;
  color: #333;
  font-size: 1.8rem;
  font-weight: 700;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 600;
}

.form-group input {
  width: 100%;
  padding: 12px;
  border: 2px solid #e1e5e9;
  border-radius: 8px;
  font-size: 16px;
  transition: all 0.3s ease;
  box-sizing: border-box;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group input.error {
  border-color: #e74c3c;
}

.error-message {
  color: #e74c3c;
  font-size: 14px;
  margin-top: 5px;
}

.submit-button,
.cancel-button {
  width: 48%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
}

.submit-button {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  margin-right: 4%;
}

.submit-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
}

.submit-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.cancel-button {
  background: #f1f5f9;
  color: #64748b;
}

.cancel-button:hover:not(:disabled) {
  background: #e2e8f0;
}

.form-error {
  color: #e74c3c;
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
}

.form-success {
  color: #2ecc71;
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
}
</style>