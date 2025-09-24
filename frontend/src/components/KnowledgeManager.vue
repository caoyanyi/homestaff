<template>
  <div class="knowledge-manager">
    <div class="manager-header">
      <h2 class="manager-title">知识库管理</h2>
      <div class="header-actions">
        <button 
          @click="refreshKnowledge" 
          :disabled="loading || submitting"
          class="refresh-button"
        >
          <span v-if="loading" class="refresh-spinner"></span>
          刷新
        </button>
      </div>
    </div>
    
    <!-- 添加知识表单 -->
    <div class="form-container">
      <div class="form-card">
        <h3 class="form-title">添加知识条目</h3>
        
        <form @submit.prevent="addKnowledge" class="knowledge-form">
          <div class="form-row">
            <div class="form-group">
              <label for="title">标题 <span class="required">*</span></label>
              <input 
                id="title"
                v-model="title"
                type="text"
                placeholder="请输入知识标题"
                required
                :disabled="submitting"
                class="form-input"
              />
            </div>
            
            <div class="form-group">
              <label for="category">分类</label>
              <input 
                id="category"
                v-model="category"
                type="text"
                placeholder="请输入分类"
                :disabled="submitting"
                class="form-input"
              />
            </div>
          </div>
          
          <div class="form-group">
            <label for="tags">标签</label>
            <input 
              id="tags"
              v-model="tags"
              type="text"
              placeholder="请输入标签，使用逗号分隔"
              :disabled="submitting"
              class="form-input"
            />
          </div>
          
          <div class="form-group">
            <label for="content">内容 <span class="required">*</span></label>
            <textarea 
              id="content"
              v-model="content"
              placeholder="请输入知识内容..."
              rows="6"
              required
              :disabled="submitting"
              class="form-textarea"
            ></textarea>
          </div>
          
          <div class="form-actions">
            <button 
              type="submit"
              :disabled="!canSubmit || submitting"
              class="submit-button"
            >
              <span v-if="submitting" class="submit-spinner"></span>
              {{ submitting ? '添加中...' : '添加知识' }}
            </button>
            <button 
              type="button"
              @click="clearForm"
              :disabled="submitting"
              class="cancel-button"
            >
              清空表单
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- 知识列表 -->
    <div class="list-container">
      <div class="list-header">
        <h3>知识列表</h3>
        <div class="knowledge-count">
          共 {{ knowledgeList.length }} 条知识
        </div>
      </div>
      
      <div v-if="loading && !submitting" class="list-loading">
        <div class="loading-spinner"></div>
        <span>加载中...</span>
      </div>
      
      <div v-else-if="knowledgeList.length === 0" class="empty-list">
        <div class="empty-icon">📚</div>
        <p>暂无知识条目</p>
        <small>添加知识后，AI 可以更好地回答相关问题</small>
      </div>
      
      <div v-else class="knowledge-list">
        <div 
          v-for="item in knowledgeList" 
          :key="item.id" 
          class="knowledge-card"
          @mouseenter="hoveredItemId = item.id"
          @mouseleave="hoveredItemId = null"
        >
          <div class="card-header">
            <h4 class="card-title">{{ item.title }}</h4>
            <div class="card-actions" :class="{ 'visible': hoveredItemId === item.id }">
              <button 
                @click.stop="editItem(item)"
                class="edit-button"
                title="编辑"
              >
                ✏️
              </button>
              <button 
                @click.stop="deleteItem(item.id)"
                class="delete-button"
                title="删除"
              >
                🗑️
              </button>
            </div>
          </div>
          
          <div class="card-content">
            <p class="content-text">{{ truncateText(item.content, 150) }}</p>
            
            <div class="card-meta">
              <span v-if="item.category" class="category-tag">
                {{ item.category }}
              </span>
              
              <div v-if="item.tags" class="tags-container">
                <span 
                  v-for="(tag, index) in parseTags(item.tags)" 
                  :key="index" 
                  class="tag"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>
          
          <div class="card-footer">
            <span class="word-count">{{ item.content.length }} 字</span>
            <span class="item-id">ID: {{ item.id }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- 编辑弹窗 -->
    <div v-if="editingItem" class="modal-overlay" @click="closeEditModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>编辑知识</h3>
          <button @click="closeEditModal" class="close-button">×</button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label>标题 <span class="required">*</span></label>
            <input 
              v-model="editingItem.title"
              type="text"
              :disabled="savingEdit"
              required
              class="form-input"
            />
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label>分类</label>
              <input 
                v-model="editingItem.category"
                type="text"
                :disabled="savingEdit"
                class="form-input"
              />
            </div>
            
            <div class="form-group">
              <label>标签</label>
              <input 
                v-model="editingItem.tags"
                type="text"
                placeholder="使用逗号分隔"
                :disabled="savingEdit"
                class="form-input"
              />
            </div>
          </div>
          
          <div class="form-group">
            <label>内容 <span class="required">*</span></label>
            <textarea 
              v-model="editingItem.content"
              rows="8"
              :disabled="savingEdit"
              required
              class="form-textarea"
            ></textarea>
          </div>
        </div>
        
        <div class="modal-footer">
          <button 
            @click="saveEdit"
            :disabled="!canSaveEdit || savingEdit"
            class="save-button"
          >
            <span v-if="savingEdit" class="save-spinner"></span>
            {{ savingEdit ? '保存中...' : '保存' }}
          </button>
          <button 
            @click="closeEditModal"
            :disabled="savingEdit"
            class="cancel-button"
          >
            取消
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      title: '',
      content: '',
      tags: '',
      category: '',
      knowledgeList: [],
      loading: false,
      submitting: false,
      hoveredItemId: null,
      editingItem: null,
      savingEdit: false
    };
  },
  computed: {
    canSubmit() {
      return this.title.trim() && this.content.trim();
    },
    canSaveEdit() {
      return this.editingItem && this.editingItem.title.trim() && this.editingItem.content.trim();
    }
  },
  mounted() {
    this.fetchKnowledge();
  },
  methods: {
    fetchKnowledge() {
      this.loading = true;
      axios.get('/api/admin/knowledge')
        .then(res => {
          this.knowledgeList = res.data || [];
        })
        .catch(error => {
          console.error('获取知识失败:', error);
          // 可以添加全局提示
        })
        .finally(() => {
          this.loading = false;
        });
    },
    
    refreshKnowledge() {
      this.fetchKnowledge();
    },
    
    addKnowledge() {
      if (!this.canSubmit || this.submitting) return;
      
      this.submitting = true;
      axios.post('/api/admin/knowledge', {
        title: this.title.trim(),
        content: this.content.trim(),
        tags: this.tags.trim(),
        category: this.category.trim()
      })
        .then(() => {
          this.fetchKnowledge();
          this.clearForm();
          // 添加成功提示
        })
        .catch(error => {
          console.error('添加知识失败:', error);
          // 添加失败提示
        })
        .finally(() => {
          this.submitting = false;
        });
    },
    
    clearForm() {
      this.title = '';
      this.content = '';
      this.tags = '';
      this.category = '';
    },
    
    parseTags(tagsString) {
      if (!tagsString) return [];
      return tagsString.split(',').map(tag => tag.trim()).filter(tag => tag);
    },
    
    truncateText(text, maxLength) {
      if (!text) return '';
      if (text.length <= maxLength) return text;
      return text.substring(0, maxLength) + '...';
    },
    
    editItem(item) {
      // 创建深拷贝
      this.editingItem = JSON.parse(JSON.stringify(item));
    },
    
    closeEditModal() {
      this.editingItem = null;
      this.savingEdit = false;
    },
    
    saveEdit() {
      if (!this.canSaveEdit || this.savingEdit) return;
      
      this.savingEdit = true;
      axios.post('/api/admin/knowledge/update', {
        id: this.editingItem.id,
        title: this.editingItem.title.trim(),
        content: this.editingItem.content.trim(),
        tags: this.editingItem.tags.trim(),
        category: this.editingItem.category.trim()
      })
        .then(() => {
          this.fetchKnowledge();
          this.closeEditModal();
          // 保存成功提示
        })
        .catch(error => {
          console.error('保存知识失败:', error);
          // 保存失败提示
        })
        .finally(() => {
          this.savingEdit = false;
        });
    },
    
    deleteItem(id) {
      if (confirm('确定要删除这条知识吗？此操作不可恢复。')) {
        axios.post('/api/admin/knowledge/delete', { id })
          .then(() => {
            this.fetchKnowledge();
            // 删除成功提示
          })
          .catch(error => {
            console.error('删除知识失败:', error);
            // 删除失败提示
          });
      }
    }
  }
};
</script>

<style scoped>
.knowledge-manager {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.manager-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.manager-title {
  color: #333;
  font-size: 1.8rem;
  font-weight: 600;
  margin: 0;
}

.refresh-button {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.refresh-button:hover:not(:disabled) {
  background: #5a67d8;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
}

.refresh-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.refresh-spinner,
.submit-spinner,
.save-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top: 2px solid white;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* 表单样式 */
.form-container {
  margin-bottom: 40px;
}

.form-card {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transition: box-shadow 0.3s ease;
}

.form-card:hover {
  box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
}

.form-title {
  color: #333;
  font-size: 1.3rem;
  margin-bottom: 25px;
  font-weight: 600;
}

.knowledge-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  color: #555;
  font-weight: 500;
  font-size: 14px;
}

.required {
  color: #ef4444;
}

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e1e5e9;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  transition: all 0.3s ease;
  box-sizing: border-box;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-input:disabled,
.form-textarea:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
  border-color: #d1d5db;
}

.form-textarea {
  resize: vertical;
  min-height: 150px;
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 10px;
}

.submit-button,
.save-button {
  padding: 12px 24px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.submit-button:hover:not(:disabled),
.save-button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.submit-button:disabled,
.save-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.cancel-button {
  padding: 12px 24px;
  background: #f3f4f6;
  color: #4b5563;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.cancel-button:hover:not(:disabled) {
  background: #e5e7eb;
}

.cancel-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* 列表样式 */
.list-container {
  margin-top: 40px;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.list-header h3 {
  color: #333;
  font-size: 1.3rem;
  font-weight: 600;
  margin: 0;
}

.knowledge-count {
  color: #6b7280;
  font-size: 14px;
}

.list-loading,
.empty-list {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.loading-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 15px;
}

.empty-list .empty-icon {
  font-size: 4rem;
  margin-bottom: 15px;
}

.empty-list p {
  font-size: 16px;
  margin-bottom: 8px;
  color: #4b5563;
}

.empty-list small {
  color: #9ca3af;
  font-size: 14px;
}

.knowledge-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.knowledge-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  padding: 20px;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.knowledge-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
  flex-shrink: 0;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #333;
  margin: 0;
  flex: 1;
  margin-right: 10px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.card-actions {
  opacity: 0;
  transition: opacity 0.3s ease;
  display: flex;
  gap: 4px;
}

.card-actions.visible {
  opacity: 1;
}

.edit-button,
.delete-button {
  padding: 6px 8px;
  background: transparent;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  transition: background-color 0.2s ease;
}

.edit-button:hover {
  background: #f0f9ff;
}

.delete-button:hover {
  background: #fef2f2;
}

.card-content {
  flex: 1;
  margin-bottom: 15px;
}

.content-text {
  color: #4b5563;
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 12px;
  word-wrap: break-word;
}

.card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.category-tag {
  background: #e0f2fe;
  color: #0284c7;
  padding: 4px 10px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 500;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.tag {
  background: #f3f4f6;
  color: #6b7280;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 15px;
  border-top: 1px solid #f3f4f6;
  font-size: 12px;
  color: #9ca3af;
  flex-shrink: 0;
}

.word-count,
.item-id {
  font-size: 12px;
}

/* 模态框样式 */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  max-height: 80vh;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  color: #333;
  font-size: 1.2rem;
  font-weight: 600;
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  color: #9ca3af;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.close-button:hover {
  background: #f3f4f6;
  color: #4b5563;
}

.modal-body {
  padding: 30px;
  max-height: calc(80vh - 160px);
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 30px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

/* 响应式设计 */
@media (max-width: 768px) {
  .knowledge-manager {
    padding: 15px;
  }
  
  .manager-header {
    flex-direction: column;
    gap: 15px;
    align-items: flex-start;
  }
  
  .form-card {
    padding: 20px;
  }
  
  .form-row {
    grid-template-columns: 1fr;
    gap: 15px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .submit-button,
  .cancel-button,
  .save-button {
    width: 100%;
    justify-content: center;
  }
  
  .knowledge-list {
    grid-template-columns: 1fr;
  }
  
  .modal-content {
    width: 95%;
    margin: 20px;
  }
  
  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 15px 20px;
  }
}

@media (max-width: 480px) {
  .manager-title {
    font-size: 1.5rem;
  }
  
  .form-title,
  .list-header h3 {
    font-size: 1.1rem;
  }
  
  .knowledge-card {
    padding: 15px;
  }
}
</style>
