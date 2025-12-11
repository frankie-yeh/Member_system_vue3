import { createRouter, createWebHashHistory } from 'vue-router';

// -------------------------------------------------------------------
// 🚀 修正後的組件引入路徑 (皆使用相對路徑：../components/)
// -------------------------------------------------------------------

import MemberTracker from '../components/MemberTracker.vue';
import MemberRegistration from '../components/MemberRegistration.vue';
import AdminDashboard from '../components/AdminDashboard.vue';
import RevenueReport from '../components/RevenueReport.vue'; 

// 定義路由規則 (這部分保持不變)
const routes = [
    {
        path: '/',
        name: 'Tracker',
        component: MemberTracker,
        meta: { title: '蒸足館-服務總控台' }
    },
    {
        path: '/register',
        name: 'Registration',
        component: MemberRegistration,
        meta: { title: '蒸足館-會員註冊' }
    },
    {
        path: '/admin',
        name: 'AdminDashboard',
        component: AdminDashboard,
        meta: { title: '蒸足館-後台管理' }
    },
    {
        path: '/revenue',
        name: 'RevenueReport',
        component: RevenueReport,
        meta: { title: '蒸足館-營收報表' }
    },
    {
        path: '/:catchAll(.*)',
        name: 'NotFound',
        redirect: '/' 
    }
];

const router = createRouter({
    history: createWebHashHistory(), 
    routes,
});

router.beforeEach((to, from, next) => {
    document.title = to.meta.title || '會員管理系統';
    next();
});

export default router;