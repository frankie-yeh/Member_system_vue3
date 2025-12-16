import { createRouter, createWebHashHistory } from 'vue-router';

// -------------------------------------------------------------------
// 🚀 組件引入路徑
// -------------------------------------------------------------------

import MemberTracker from '../components/MemberTracker.vue';
import MemberRegistration from '../components/MemberRegistration.vue';
import AdminDashboard from '../components/AdminDashboard.vue';
import RevenueReport from '../components/RevenueReport.vue'; 
import AdminLogin from '../components/AdminLogin.vue'; 
import MemberByJoinDate from '../components/MemberByJoinDate.vue'

// -------------------------------------------------------------------
// 🚀 路由規則定義
// -------------------------------------------------------------------
const routes = [
    // 員工可訪問頁面 (不需要 requiresAdminAuth)
    {
        path: '/',
        name: 'Tracker',
        component: MemberTracker,
        meta: { title: '蒸足館-服務總控台', requiresAdminAuth: false }
    },
    {
        path: '/register',
        name: 'Registration',
        component: MemberRegistration,
        meta: { title: '蒸足館-會員註冊', requiresAdminAuth: false }
    },
    
    // 管理員頁面 (需要 requiresAdminAuth: true)
    {
        path: '/admin',
        name: 'AdminDashboard',
        component: AdminDashboard,
        meta: { title: '蒸足館-後台管理', requiresAdminAuth: true } 
    },
    {
        path: '/revenue',
        name: 'RevenueReport',
        component: RevenueReport,
        meta: { title: '蒸足館-營收報表', requiresAdminAuth: true } 
    },
    {
    path: '/admin/members-by-date',
    name: 'MembersByDate',
    component: MemberByJoinDate,
    meta: { 
        title: '蒸足館-依日期查會員', 
        requiresAdminAuth: true 
    }
},
    
    // 管理員登入頁面 (不需保護)
    {
        path: '/admin-login',
        name: 'AdminLogin',
        component: AdminLogin,
        meta: { title: '蒸足館-管理員登入', requiresAdminAuth: false }
    },

    // 404 處理
    {
        path: '/:catchAll(.*)',
        name: 'NotFound',
        redirect: '/' 
    }
];

const router = createRouter({
    history: createWebHashHistory('/app/'), 
    routes,
});

// -------------------------------------------------------------------
// 🚀 權限檢查和路由守衛邏輯
// -------------------------------------------------------------------

// 確保 API 基礎 URL 與您的部署路徑匹配
// 💡 請確保這裡的路徑與您實際部署的 API 網址匹配
const API_BASE_URL = 'https://yamay.com.tw/app'; 

// 輔助函數：向後端驗證權杖
async function validateAdminToken(token) {
    try {
        // 為了避免快取問題，我們加入了時間戳參數
        const currentTime = new Date().getTime(); 
        const response = await fetch(`${API_BASE_URL}/api.php?action=validate_token&_t=${currentTime}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `token=${token}` // 將權杖傳遞給後端
        });
        const data = await response.json();
        
        // 只有後端回傳 success 且狀態碼為 200/OK 才算有效
        return response.ok && data.status === 'success'; 
    } catch (error) {
        console.error("Token validation failed:", error);
        return false;
    }
}

router.beforeEach(async (to, from, next) => {
    // 1. 設置頁面標題
    document.title = to.meta.title || '會員管理系統';

    // 2. 檢查是否需要管理員權限
    if (to.meta.requiresAdminAuth) {
        const token = localStorage.getItem('admin_token');

        if (!token) {
            // 沒有權杖，導向登入頁
            next('/admin-login');
        } else {
            // 有權杖，進行後端驗證
            const isValid = await validateAdminToken(token);

            if (isValid) {
                // 權杖有效，放行
                next();
            } else {
                // 無效或過期，清除權杖並導向登入頁
                localStorage.removeItem('admin_token');
                // 為了避免無限循環，如果目標已經是登入頁，則直接放行 (雖然邏輯上不會發生)
                if (to.name !== 'AdminLogin') {
                    next('/admin-login');
                } else {
                    next();
                }
            }
        }
    } else {
        // 路由不需要管理員權限，直接放行 (適用於 / 和 /register)
        next();
    }
});

export default router;