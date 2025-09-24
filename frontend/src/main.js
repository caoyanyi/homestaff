import { createApp } from 'vue'
import App from './App.vue'
import axios from 'axios'
import './styles/components.css'

axios.defaults.baseURL = 'http://127.0.0.1:8000'; // Laravel 后端地址
axios.defaults.withCredentials = true;

createApp(App).mount('#app')
