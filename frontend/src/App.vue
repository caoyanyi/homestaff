<template>
  <div id="app">
    <h1>家政 AI 知识库系统</h1>
    <nav>
      <button @click="view='chat'">AI 问答</button>
      <button @click="view='manage'">管理知识库</button>
    </nav>
    <AiChat v-if="view==='chat'" />
    <KnowledgeManager v-else />
  </div>
</template>

<script>
import AiChat from './components/AiChat.vue';
import KnowledgeManager from './components/KnowledgeManager.vue';

export default {
  components: { AiChat, KnowledgeManager },
  data() {
    return { view: 'chat' };
  }
};
</script>
