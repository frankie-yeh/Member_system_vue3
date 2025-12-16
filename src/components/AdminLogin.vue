<template>
  <div class="admin-login-container">
    <h2>管理員登入</h2>
    <form @submit.prevent="handleLogin" class="login-form">
      <div class="form-group">
        <label for="username">帳號 (Username)</label>
        <input type="text" id="username" v-model="username" required>
      </div>
      
      <div class="form-group">
        <label for="password">密碼 (Password)</label>
        <input type="password" id="password" v-model="password" required>
      </div>
      
      <button type="submit" :disabled="loading">
        {{ loading ? '登入中...' : '登入' }}
      </button>

      <p v-if="errorMessage" class="error-message">{{ errorMessage }}</p>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const username = ref('');
const password = ref('');
const loading = ref(false);
const errorMessage = ref('');

// 確保與您在 api.php 中的基礎 URL 匹配
const API_BASE_URL = 'https://yamay.com.tw/app'; 

const handleLogin = async () => {
  loading.value = true;
  errorMessage.value = '';

  // 將資料轉換為 URL 編碼格式，適用於 PHP 的 $_POST
  const formData = new URLSearchParams();
  formData.append('username', username.value);
  formData.append('password', password.value);

  try {
    const response = await fetch(`${API_BASE_URL}/api.php?action=admin_login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: formData.toString(),
    });

    const data = await response.json();

    if (data.status === 'success' && data.token) {
      // 登入成功！
      // 1. 儲存權杖到本地儲存
      localStorage.setItem('admin_token', data.token);
      
      // 2. 導航到管理員頁面 (例如 /admin)
      router.push('/admin');

    } else {
      // 登入失敗 (例如 401 錯誤)
      errorMessage.value = data.message || '登入失敗，請檢查帳號和密碼。';
    }

  } catch (error) {
    console.error('Login error:', error);
    errorMessage.value = '網路連線錯誤，請稍後再試。';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
/* 簡單的樣式，您可以根據您的應用程式設計調整 */
.admin-login-container {
  max-width: 400px;
  margin: 50px auto;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  background: white;
  text-align: center;
}
.login-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.form-group {
  text-align: left;
}
label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}
input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
}
button {
  padding: 10px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.3s;
}
button:hover:not(:disabled) {
  background-color: #0056b3;
}
button:disabled {
  background-color: #a0c9f1;
  cursor: not-allowed;
}
.error-message {
  color: red;
  margin-top: 10px;
}
</style>