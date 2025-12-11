<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const API_BASE_URL = 'https://yamay.com.tw/app'; 

const router = useRouter();

// 狀態管理
const members = ref([]);
const transactions = ref([]);
const loading = ref(true);
const error = ref('');

// ----------------------------------------------------
// A. 獲取所有會員列表
// ----------------------------------------------------
const fetchAllMembers = async () => {
    try {
        const currentTime = new Date().getTime();
        const response = await fetch(`${API_BASE_URL}/api.php?action=get_all_members&_t=${currentTime}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            members.value = data.data || [];
        } else {
            error.value = `獲取會員列表失敗: ${data.message}`;
        }
    } catch (err) {
        error.value = '網路錯誤，無法連接 API 獲取會員資料。';
    }
};

// ----------------------------------------------------
// B. 獲取所有交易記錄
// ----------------------------------------------------
const fetchAllTransactions = async () => {
    try {
        const currentTime = new Date().getTime();
        const response = await fetch(`${API_BASE_URL}/api.php?action=get_all_transactions&_t=${currentTime}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const rawData = data.data || [];
            transactions.value = rawData.map(t => ({
                ...t,
                amount_paid: parseFloat(t.amount_paid) 
            }));
            
        } else {
            error.value = `獲取交易記錄失敗: ${data.message}`;
        }
    } catch (err) {
        error.value = '網路錯誤，無法連接 API 獲取交易記錄。';
    } finally {
        loading.value = false;
    }
};

// ----------------------------------------------------
// C. 格式化函數
// ----------------------------------------------------
const formatDateTime = (timestamp) => {
    if (!timestamp) return 'N/A';
    // 假設 timestamp 格式為 YYYY-MM-DD HH:MM:SS
    const date = new Date(timestamp.replace(' ', 'T'));
    return date.toLocaleString('zh-TW', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false
    });
};

const formatQuota = (quota) => {
    return quota <= 0 ? '0 次 (已用盡)' : `${quota} 次`;
};

// ----------------------------------------------------
// D. 導航和初始化
// ----------------------------------------------------
const goToRevenueReport = () => {
    router.push('/revenue');
};

const goToTracker = () => {
    router.push('/');
};

// 組件加載時自動獲取數據
onMounted(() => {
    fetchAllMembers();
    fetchAllTransactions();
});
</script>

<template>
    <div class="admin-dashboard-container card">
        <h1>📊 後台管理總覽</h1>
        
        <div class="header-controls">
            <button @click="goToRevenueReport" class="btn report-btn">
                📈 營收報表查詢
            </button>
            <button @click="goToTracker" class="btn back-btn">
                🏠 返回服務總控台
            </button>
        </div>

        <div v-if="loading" class="loading-state">
            <p>正在載入數據中...</p>
        </div>
        
        <div v-else-if="error" class="error-state">
            <p>{{ error }}</p>
        </div>

        <div v-else class="data-sections">
            <div class="section member-list">
                <h2>會員列表 (共 {{ members.length }} 位)</h2>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>姓名</th>
                                <th>電話</th>
                                <th>剩餘額度</th>
                                <th>服務方案</th>
                                <th>加入日期</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id">
                                <td>{{ member.name }}</td>
                                <td>{{ member.phone }}</td>
                                <td :class="{'low-quota': member.remaining_quota < 3}">
                                    {{ formatQuota(member.remaining_quota) }}
                                </td>
                                <td>{{ member.service_name }}</td>
                                <td>{{ member.join_date.substring(0, 10) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="members.length === 0">目前沒有會員資料。</p>
            </div>

            <hr>

            <div class="section transaction-list">
                <h2>最新交易記錄 (最近 {{ transactions.length }} 筆)</h2>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>時間</th>
                                <th>類型</th>
                                <th>會員</th>
                                <th>操作員</th>
                                <th>服務項目</th>
                                <th>收費金額</th>
                                <th>扣減次數</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transactions" :key="t.id">
                                <td>{{ formatDateTime(t.transaction_date) }}</td>
                                <td>
                                    <span :class="{'type-member': t.customer_type === 'MEMBER', 'type-non-member': t.customer_type === 'NON_MEMBER'}">
                                        {{ t.customer_type === 'MEMBER' ? '會員服務' : '非會員' }}
                                    </span>
                                </td>
                                <td>{{ t.customer_type === 'MEMBER' ? t.member_name : 'N/A' }}</td>
                                <td>{{ t.operator }}</td>
                                <td>{{ t.service_name }}</td>
                                <td :class="{'revenue-amount': t.amount_paid > 0}">
                                    ${{ t.amount_paid.toFixed(2) }}
                                </td>
                                <td>{{ t.quota_deducted }} 次</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="transactions.length === 0">目前沒有交易記錄。</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* 基礎樣式 */
.card {
    background-color: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}
.admin-dashboard-container { max-width: 1200px; margin: 20px auto; }
h1 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }

/* 控制按鈕 */
.header-controls {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
}
.btn { 
    padding: 10px 15px; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    color: white; 
    font-weight: bold; 
    transition: background-color 0.3s;
}
.report-btn { background-color: #007bff; }
.back-btn { background-color: #6c757d; }

/* 數據區塊 */
.section h2 { margin-top: 0; color: #555; }
.table-scroll { max-height: 500px; overflow-y: auto; margin-top: 15px; }

/* 表格樣式 */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9em;
}
th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
th {
    background-color: #f8f9fa;
    color: #333;
    position: sticky; /* 固定表頭 */
    top: 0;
}
tbody tr:hover { background-color: #f1f1f1; }

/* 特殊樣式 */
.low-quota { color: #dc3545; font-weight: bold; }
.revenue-amount { color: #28a745; font-weight: bold; }
.type-member { color: #007bff; }
.type-non-member { color: #ffc107; }

.loading-state, .error-state {
    padding: 20px;
    text-align: center;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    margin-top: 20px;
}
.error-state p { color: #dc3545; font-weight: bold; }
</style>