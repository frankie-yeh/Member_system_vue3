<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const API_BASE_URL = 'https://yamay.com.tw/app'; 

const router = useRouter();

// 狀態管理
const queryType = ref('day'); // 預設查詢模式：日(day) 或 月(month)
const queryDate = ref(new Date().toISOString().substring(0, 10)); // 預設為當前日期 (YYYY-MM-DD)
const reportData = ref(null);
const message = ref('');
const loading = ref(false);

// ----------------------------------------------------
// A. 處理營收查詢
// ----------------------------------------------------
const fetchRevenue = async () => {
    message.value = '';
    reportData.value = null;
    loading.value = true;

    if (!queryDate.value) {
        message.value = '⚠️ 請選擇查詢日期。';
        loading.value = false;
        return;
    }

    // 根據查詢模式決定 API action
    const action = queryType.value === 'month' ? 'get_monthly_revenue' : 'get_daily_revenue';
    
    // 如果是月查詢，只需要年月部分
    const dateParam = queryType.value === 'month' ? queryDate.value.substring(0, 7) : queryDate.value;

    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=${action}&date=${dateParam}`);
        const data = await response.json();

        if (data.status === 'success') {
            reportData.value = data.data;
            message.value = `✅ ${reportData.value.period} 的營收數據載入成功。`;
        } else {
            message.value = `❌ 查詢失敗: ${data.message}`;
        }
    } catch (error) {
        message.value = '網路錯誤，營收查詢失敗。';
    } finally {
        loading.value = false;
    }
};

// ----------------------------------------------------
// B. 輔助函數
// ----------------------------------------------------
const formatCurrency = (amount) => {
    // 格式化為台幣 NT$ 符號
    return `NT$ ${parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
};

// ----------------------------------------------------
// C. 導航
// ----------------------------------------------------
const goToDashboard = () => {
    router.push('/admin');
};
</script>

<template>
    <div class="revenue-report-container card">
        <h1>📈 營收報表查詢</h1>

        <div class="header-controls">
            <button @click="goToDashboard" class="btn back-btn">
                🔙 返回後台總覽
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

            <button @click="fetchRevenue" :disabled="loading" class="btn query-btn">
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
</style>