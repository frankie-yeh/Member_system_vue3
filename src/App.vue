<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
    <div id="app">
        <header>
            <nav class="navbar">
                <RouterLink to="/" class="nav-brand">蒸足館 會員管理系統</RouterLink>
                <div class="nav-links">
                    <RouterLink to="/" class="nav-item">總控台 (日常操作)</RouterLink>
                    <RouterLink to="/register" class="nav-item">會員註冊 ($3000)</RouterLink>
                    <RouterLink to="/admin" class="nav-item">後台管理</RouterLink>
                    <RouterLink to="/revenue" class="nav-item">營收報表</RouterLink>
                </div>
            </nav>
        </header>

        <main class="content-wrapper">
            <RouterView />
        </main>
        
        <footer>
            <p>© 2025 蒸足館 會員管理系統</p>
        </footer>
    </div>
</template>

<style>
/* 全局基礎樣式 */
body {
    font-family: 'Arial', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f7f6;
    color: #333;
}
#app {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* 導航欄樣式 */
header {
    background-color: #343a40;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    color: white;
}
.navbar {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
}
.nav-brand {
    font-size: 1.5em;
    font-weight: bold;
    color: white;
    text-decoration: none;
    padding-right: 30px;
}
.nav-links {
    display: flex;
    gap: 20px;
}
.nav-item {
    color: #ccc;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 4px;
    transition: background-color 0.3s, color 0.3s;
}
.nav-item:hover,
.nav-item.router-link-active {
    background-color: #007bff;
    color: white;
}

/* 內容區域樣式 */
.content-wrapper {
    flex-grow: 1;
    padding: 20px;
}

/* Footer 樣式 */
footer {
    padding: 15px;
    text-align: center;
    background-color: #e9ecef;
    color: #6c757d;
    font-size: 0.9em;
    margin-top: auto;
}
</style>