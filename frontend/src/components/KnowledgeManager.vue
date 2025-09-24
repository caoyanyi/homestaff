<template>
  <div class="knowledge-manager">
    <h2>知识库管理</h2>
    <form @submit.prevent="addKnowledge">
      <input v-model="title" placeholder="标题" required/>
      <textarea v-model="content" placeholder="内容" required></textarea>
      <input v-model="tags" placeholder="标签(,分隔)"/>
      <input v-model="category" placeholder="分类"/>
      <button type="submit">添加</button>
    </form>

    <ul>
      <li v-for="item in knowledgeList" :key="item.id">
        <strong>{{ item.title }}</strong> - {{ item.category }}
      </li>
    </ul>
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
      knowledgeList: []
    };
  },
  mounted() {
    this.fetchKnowledge();
  },
  methods: {
    addKnowledge() {
      axios.post('/api/admin/knowledge', {
        title: this.title,
        content: this.content,
        tags: this.tags,
        category: this.category
      }).then(() => {
        this.fetchKnowledge();
      });
    },
    fetchKnowledge() {
      axios.get('/api/admin/knowledge').then(res => {
        this.knowledgeList = res.data;
      });
    }
  }
};
</script>
