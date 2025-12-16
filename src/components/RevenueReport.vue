<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const API_BASE_URL = 'https://yamay.com.tw/app'
const router = useRouter()

/* =========================
   狀態
========================= */
const queryType = ref('day')
const queryDate = ref(new Date().toISOString().substring(0, 10))
const loading = ref(false)
const message = ref('')

const reportData = ref(null)
const visitorStats = ref({
    member_count: 0,
    non_member_count: 0,
    new_member_count: 0,
    total_visitors: 0,
})

/* =========================
   計算屬性
========================= */
const isDailyReport = computed(() => queryType.value === 'day')

/* =========================
   工具
========================= */
const formatCurrency = (amount) => {
    const value = Number(amount ?? 0)
    return `NT$ ${value.toLocaleString('zh-TW', { minimumFractionDigits: 2 })}`
}

/* =========================
   主查詢（唯一入口）
========================= */
const fetchReport = async () => {
    loading.value = true
    message.value = ''
    reportData.value = null
    visitorStats.value = {
        member_count: 0,
        non_member_count: 0,
        new_member_count: 0,
        total_visitors: 0,
    }

    if (!queryDate.value) {
        message.value = '⚠️ 請選擇查詢日期'
        loading.value = false
        return
    }

    const isDaily = queryType.value === 'day'
    const action = isDaily ? 'get_daily_revenue' : 'get_monthly_revenue'
    const dateParam = isDaily ? queryDate.value : queryDate.value.substring(0, 7)

    try {
        const res = await fetch(
            `${API_BASE_URL}/api.php?action=${action}&date=${dateParam}`
        )

        if (!res.ok) {
            message.value = `❌ API 錯誤 (${res.status})`
            return
        }

        const data = await res.json()
        console.log('API 回傳：', data)

        if (data.status !== 'success' || !data.data) {
            message.value = `❌ 查詢失敗：${data.message || '未知錯誤'}`
            return
        }

        // ✅ 寫入唯一狀態
        reportData.value = data.data
        visitorStats.value = data.data.visitor_stats ?? visitorStats.value

        message.value = `✅ ${data.data.period ?? dateParam} 的報表載入成功`
    } catch (err) {
        console.error(err)
        message.value = '❌ 網路錯誤，無法連線 API'
    } finally {
        loading.value = false
    }
}

/* =========================
   導航 / 登出
========================= */
const goToDashboard = () => router.push('/admin')

const handleLogout = async () => {
    const token = localStorage.getItem('admin_token')
    localStorage.removeItem('admin_token')

    if (token) {
        try {
            await fetch(`${API_BASE_URL}/api.php?action=admin_logout`, {
                method: 'POST',
                headers: { Authorization: `Bearer ${token}` },
            })
        } catch (err) {
            console.warn('Logout API failed:', err)
        }
    }

    router.push('/')
}

/* =========================
   初始化
========================= */
onMounted(fetchReport)
</script>


<template>
    <div class="revenue-report-container card">
        <h1>📈 營收報表查詢</h1>

        <div class="header-controls">
            <button @click="goToDashboard" class="btn back-btn">
                🔙 返回後台總覽
            </button>
            <button @click="handleLogout" class="logout-btn-fixed">
                登出管理員帳號
            </button>
        </div>
        
        <div v-if="message" :class="['message', message.startsWith('✅') ? 'success' : 'error']">
            {{ message }}
        </div>

        <div class="query-form">
            <div class="form-group">
                <label for="queryType">查詢模式：</label>
                <select id="queryType" v-model="queryType">
                    <option value="day">日報表 (單日)</option>
                    <option value="month">月報表 (整月)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="queryDate">選擇日期：</label>
                <input 
                    type="date" 
                    id="queryDate" 
                    v-model="queryDate" 
                    :max="new Date().toISOString().substring(0, 10)"
                >
            </div>

            <button @click="fetchReport" :disabled="loading" class="btn query-btn">
                {{ loading ? '查詢中...' : '執行營收查詢' }}
            </button>
        </div>

        <hr>

        <div v-if="reportData" class="report-result">
            <h2>{{ reportData.period }} 營收數據</h2>
            
            <div class="revenue-boxes">
                <div class="revenue-box total-box">
                    <h3>總營收</h3>
                    <p class="amount-lg">{{ formatCurrency(reportData.total_revenue) }}</p>
                </div>

                <div class="revenue-box member-fee-box">
                    <h3>會員費收入 ( $3000 )</h3>
                    <p class="amount-md">{{ formatCurrency(reportData.member_fee_revenue) }}</p>
                    <p class="source-hint">來源：會員註冊/續約 $3000 費用</p>
                </div>

                <div class="revenue-box non-member-box">
                    <h3>非會員服務收入</h3>
                    <p class="amount-md">{{ formatCurrency(reportData.non_member_revenue) }}</p>
                    <p class="source-hint">來源：單次消費 $399 / $499</p>
                </div>
            </div>
        </div>

        <div v-else-if="!loading" class="no-data-hint">
            <p>請選擇日期和模式，執行查詢以生成報表。</p>
        </div>
    </div>
    <div class="section visitor-stats-summary">
        <h2>👥 來客數分析 ({{ isDailyReport ? '當日' : '當月' }})</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>會員來客數</h3>
                <p class="count member-count">{{ visitorStats.member_count }} 位</p>
            </div>
            <div class="stat-card">
                <h3>非會員來客數</h3>
                <p class="count non-member-count">{{ visitorStats.non_member_count }} 位</p>
            </div>
            <div class="stat-card primary">
                <h3>總來客數 (總人流)</h3>
                <p class="count total-count">{{ visitorStats.total_visitors }} 位</p>
            </div>
            <div class="stat-card new">
                <h3>新加入會員</h3>
                <p class="count new-count">{{ visitorStats.new_member_count }} 位</p>
            </div>
        </div>
    </div>
    
    <hr>
</template>

<style scoped>
/* 基礎樣式 */
.card {
    background-color: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}
.revenue-report-container { max-width: 900px; margin: 20px auto; }
h1 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }

/* 訊息和控制 */
.message { padding: 10px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.header-controls { margin-bottom: 20px; }
.btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; color: white; font-weight: bold; }
.back-btn { background-color: #6c757d; }
.query-btn { background-color: #007bff; }
.query-btn:disabled { background-color: #ccc; }

/* 查詢表單 */
.query-form {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label { margin-bottom: 5px; font-weight: bold; color: #555; font-size: 0.9em; }
.form-group select, .form-group input[type="date"] {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1em;
}

/* 報表結果 */
.report-result h2 { 
    text-align: center; 
    color: #007bff; 
    margin-bottom: 25px; 
}
.revenue-boxes {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
}
.revenue-box {
    padding: 25px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}
.revenue-box h3 { margin-top: 0; font-size: 1.1em; color: #333; }
.amount-lg { font-size: 2em; font-weight: bold; }
.amount-md { font-size: 1.5em; font-weight: bold; }
.source-hint { font-size: 0.8em; color: #888; margin-top: 10px; }

.total-box { 
    background-color: #e6f7ff; 
    border: 2px solid #007bff;
    grid-column: 1 / -1; /* 總收入佔滿整行 */
}
.total-box .amount-lg { color: #007bff; }

.member-fee-box { 
    background-color: #e6ffe6; 
    border-left: 5px solid #28a745;
}
.member-fee-box .amount-md { color: #28a745; }

.non-member-box { 
    background-color: #fff8e6; 
    border-left: 5px solid #ff9800;
}
.non-member-box .amount-md { color: #ff9800; }

.no-data-hint {
    text-align: center;
    padding: 50px;
    color: #888;
    border: 1px dashed #ccc;
    border-radius: 8px;
    margin-top: 20px;
}
.header-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    margin-bottom: 20px;
}
.logout-btn-fixed {
    background-color: #dc3545; /* 紅色 */
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
}
/* 人數統計網格佈局 */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* 四個欄位 */
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.stat-card h3 {
    font-size: 1em;
    color: #666;
    margin-bottom: 5px;
}

.stat-card .count {
    font-size: 1.8em;
    font-weight: 700;
    margin: 0;
}

/* 突顯總數和新增數 */
.stat-card.primary {
    background-color: #007bff;
    color: white;
}
.stat-card.primary h3 {
    color: white;
}
.stat-card.new {
    background-color: #28a745;
    color: white;
}
.stat-card.new h3 {
    color: white;
}
</style>