<template>
  <div class="ai-chat">
    <h2>家政 AI 问答</h2>
    <textarea v-model="question" placeholder="请输入您的问题..." />
    <button @click="ask">发送</button>

    <div v-if="loading">AI 正在思考...</div>

    <div v-if="answer">
      <h3>AI 的回答：</h3>
      <p>{{ answer }}</p>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      question: '',
      answer: '',
      loading: false
    };
  },
  methods: {
    ask() {
      this.loading = true;
      // 假设已有登录且 token 设置到 axios
      axios.post('/api/ai/ask', { question: this.question })
        .then(res => {
          this.answer = res.data.answer;
        })
        .finally(() => {
          this.loading = false;
        });
    }
  }
};
</script>
