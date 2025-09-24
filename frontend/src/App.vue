<template>
  <div id="app">
    <header class="app-header" v-if="!showLogin && !showChangePassword">
      <h1>家政 AI 知识库系统</h1>
      <div class="header-actions">
        <template v-if="isAuthenticated">
          <span class="user-info">欢迎，{{ user?.name || '用户' }}</span>
          <button @click="showChangePassword = true" class="change-password-button">修改密码</button>
          <button @click="logout" class="logout-button">退出登录</button>
        </template>
        <template v-else>
          <button @click="showLogin = true" class="login-button">登录</button>
        </template>
      </div>
    </header>
    
    <nav class="main-nav" v-if="isAuthenticated && !showLogin && !showChangePassword">
      <button 
        :class="{ 'active': view === 'chat' }"
        @click="view='chat'"
      >
        AI 问答
      </button>
      <button 
        :class="{ 'active': view === 'manage' }"
        @click="view='manage'"
      >
        管理知识库
      </button>
    </nav>
    
    <main class="app-main" v-if="!showLogin && !showChangePassword">
      <AiChat />
      <KnowledgeManager v-if="isAuthenticated && view === 'manage'" />
    </main>
    
    <Login 
      v-if="showLogin"
      @login-success="handleLoginSuccess"
      @cancel="showLogin = false"
    />
    
    <ChangePassword 
      v-if="showChangePassword"
      @cancel="showChangePassword = false"
      @password-updated="handlePasswordUpdated"
    />
  </div>
</template>

<script>
import AiChat from './components/AiChat.vue';
import KnowledgeManager from './components/KnowledgeManager.vue';
import Login from './components/Login.vue';
import ChangePassword from './components/ChangePassword.vue';
import axios from 'axios';

export default {
  components: { AiChat, KnowledgeManager, Login, ChangePassword },
  data() {
    return {
      view: 'chat',
      isAuthenticated: false,
      user: null,
      showChangePassword: false,
      showLogin: false
    };
  },
  mounted() {
    this.checkAuth();
  },
  methods: {
    checkAuth() {
      const token = localStorage.getItem('auth_token');
      const user = localStorage.getItem('user');
      
      if (token && user) {
        this.isAuthenticated = true;
        this.user = JSON.parse(user);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        this.view = 'chat';
      }
    },
    handleLoginSuccess() {
      this.checkAuth();
      this.showLogin = false;
    },
    logout() {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      delete axios.defaults.headers.common['Authorization'];
      this.isAuthenticated = false;
      this.user = null;
      this.view = 'chat';
      this.showChangePassword = false;
    },
    handlePasswordUpdated() {
      // 密码修改成功后，强制用户重新登录
      this.logout();
    }
  }
};
</script>

<style scoped>
.app-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-radius: 0 0 16px 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  margin-bottom: 20px;
}

.app-header h1 {
  margin: 0;
  font-size: 1.8rem;
  font-weight: 700;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;

  .change-password-button {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;

    &:hover {
      background: #45a049;
    }
  }


  align-items: center;
  gap: 15px;
}

.user-info {
  font-size: 14px;
  opacity: 0.9;
}

.logout-button {
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: white;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.3s ease;
}

.login-button {
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: white;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.3s ease;
}

.logout-button:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-1px);
}

.main-nav {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  justify-content: center;
}

.main-nav button {
  padding: 12px 24px;
  background: white;
  border: 2px solid #e1e5e9;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 500;
  transition: all 0.3s ease;
  color: #333;
}

.main-nav button:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.main-nav button.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-color: #667eea;
  color: white;
}

.app-main {
  background: white;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  min-height: 60vh;
}

@media (max-width: 768px) {
  .app-header {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }
  
  .app-header h1 {
    font-size: 1.5rem;
  }
  
  .header-actions {
    flex-direction: column;
    gap: 10px;
  }
  
  .main-nav {
    flex-direction: column;
    align-items: center;
  }
  
  .main-nav button {
    width: 100%;
    max-width: 300px;
  }
  
  .app-main {
    padding: 20px;
  }
}
</style>
