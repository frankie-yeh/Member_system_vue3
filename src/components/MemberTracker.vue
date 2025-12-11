<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'; 

const router = useRouter();

const API_BASE_URL = 'https://yamay.com.tw/app'; 

// 狀態管理
const searchQuery = ref('');
const searchResult = ref(null); 
const message = ref('');
const operator = ref('Admin'); 

// ----------------------------------------------------
// A. 非會員單次消費 (直接收費並記錄)
// ----------------------------------------------------
const handleNonMemberTransaction = async (productId, price, serviceName) => {
    message.value = '';
    if (!operator.value) {
        message.value = '⚠️ 請填寫操作員姓名。';
        return;
    }
    
    if (!confirm(`確定要為非會員收費 $${price}，並記錄 ${serviceName} 服務嗎？`)) {
        return;
    }

    // 呼叫 record_transaction API 記錄非會員收費
    const payload = {
        customer_type: 'NON_MEMBER',
        product_id: productId, // 1 或 2
        operator: operator.value,
        amount_paid: price 
    };

    try {
        // 🚀 修正 2: 修正 fetch 呼叫路徑
        const response = await fetch(`${API_BASE_URL}/api.php?action=record_transaction`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            message.value = `✅ 單次服務收費 $${price} 記錄成功！請收取現金。`;
        } else {
            message.value = `❌ 交易失敗: ${data.message}`;
        }
    } catch (error) {
        message.value = '網路錯誤，單次消費記錄失敗。';
    }
    setTimeout(() => { message.value = ''; }, 8000);
};


// ----------------------------------------------------
// B. 會員查詢功能 (調閱資料)
// ----------------------------------------------------
const searchMember = async () => {
    message.value = '';
    searchResult.value = null; // 清空上次結果

    if (!searchQuery.value) {
        message.value = '⚠️ 請輸入會員姓名或電話進行查詢。';
        return;
    }

    try {
        const encodedQuery = encodeURIComponent(searchQuery.value);
        // 🚀 修正 3: 修正 searchMember 的 API 呼叫路徑
        const response = await fetch(`${API_BASE_URL}/api.php?action=search_member&query=${encodedQuery}`);
        const data = await response.json();

        if (data.data) {
            searchResult.value = data.data;
            message.value = `🟢 找到會員：${data.data.name}。剩餘 ${data.data.remaining_quota} 次。`;
        } else {
            message.value = `🟡 查無此會員。請引導客戶加入會員。`;
            searchResult.value = null;
        }
    } catch (error) {
        message.value = '查詢時發生網路錯誤。';
    }
};

// ----------------------------------------------------
// C. 會員扣次 (服務完成)
// ----------------------------------------------------
const deductQuota = async () => {
    if (!searchResult.value || searchResult.value.remaining_quota < 1) {
        message.value = '⚠️ 額度不足或未查詢到會員。';
        return;
    }
    if (!operator.value) {
        message.value = '⚠️ 請填寫操作員姓名。';
        return;
    }

    if (!confirm(`確定要為會員 ${searchResult.value.name} 扣減 1 次服務額度嗎？`)) {
        return;
    }

    // 呼叫 record_transaction API 進行扣次
    const payload = {
        customer_type: 'MEMBER',
        member_id: searchResult.value.id,
        // 會員額度關聯的 product_id，用於記錄交易類型
        product_id: searchResult.value.associated_product_id, 
        operator: operator.value,
        // amount_paid=0, quota_deducted=1，後端會處理
    };

    try {
        // 🚀 修正 4: 修正 deductQuota 的 API 呼叫路徑
        const response = await fetch(`${API_BASE_URL}/api.php?action=record_transaction`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();

        if (data.status === 'success') {
            message.value = `✅ 會員 ${searchResult.value.name} 服務完成，已扣除 1 次額度！`;
            // 重新查詢更新餘額
            searchMember(); 
        } else {
            message.value = `❌ 扣次失敗：${data.message}`;
        }
    } catch (error) {
        message.value = '網路錯誤，扣次失敗。';
    }
    setTimeout(() => { message.value = ''; }, 8000);
};

// ----------------------------------------------------
// D. 會員續約/重新儲值 ($3000)
// ----------------------------------------------------
const handleRenew = async () => {
    if (!searchResult.value || !operator.value) {
        message.value = '⚠️ 請先查詢會員並填寫操作員姓名。';
        return;
    }

    if (!confirm(`確定要為會員 ${searchResult.value.name} 辦理 $3000 續約儲值 10 次嗎？`)) {
        return;
    }
    
    const payload = {
        member_id: searchResult.value.id,
        operator: operator.value,
    };

    try {
        // 🚀 修正 5: 修正 handleRenew 的 API 呼叫路徑
        const response = await fetch(`${API_BASE_URL}/api.php?action=renew_member`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();

        if (data.status === 'success') {
            message.value = `✅ ${data.message}`;
            // 續約成功後，重新查詢以更新餘額顯示
            searchMember(); 
        } else {
            message.value = `❌ 續約失敗：${data.message}`;
        }
    } catch (error) {
        message.value = '網路錯誤，續約儲值失敗。';
    }
    setTimeout(() => { message.value = ''; }, 8000);
};

// ----------------------------------------------------
// E. 前端導航控制 (Router 跳轉)
// ----------------------------------------------------
const goToRegistration = () => {
    router.push('/register'); // 跳轉到會員註冊頁
};
const goToAdminDashboard = () => {
    router.push('/admin'); // 跳轉到後台管理頁
};
</script>

<template>
    <div class="tracker-container card">
        <h1>🔍 服務總控台 (收費/扣次/續約)</h1>
        
        <div v-if="message" :class="['message', message.startsWith('✅') ? 'success' : 'error']">
            {{ message }}
        </div>

        <div class="operator-input">
            <label for="operator">操作員姓名：</label>
            <input type="text" id="operator" v-model="operator" placeholder="輸入您的姓名">
        </div>

        <hr>

        <div class="section non-member-section">
            <h2>💸 非會員單次服務 (直接收 $399/$499)</h2>
            <p>顧客不加入會員時，請點擊按鈕完成收費和記錄。</p>
            <div class="service-buttons">
                <button @click="handleNonMemberTransaction(1, 399.00, '標準服務')" class="btn service-399">
                    標準服務 ($399)
                </button>
                <button @click="handleNonMemberTransaction(2, 499.00, '進階服務')" class="btn service-499">
                    進階服務 ($499)
                </button>
            </div>
        </div>

        <hr>

        <div class="section member-section">
            <h2>🤝 會員服務與儲值 (扣次/續約 $3000)</h2>
            <div class="search-area">
                <input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="輸入會員姓名或電話"
                    @keyup.enter="searchMember"
                >
                <button @click="searchMember" class="btn search-btn">查詢會員資料</button>
            </div>

            <div v-if="searchResult" class="result-box success">
                <h3>會員資訊：{{ searchResult.name }}</h3>
                <p>📞 {{ searchResult.phone }}</p>
                <p>📌 方案類型：{{ searchResult.service_name }}</p>
                
                <p class="quota-display">
                    剩餘額度：
                    <span :class="{'low-quota': searchResult.remaining_quota < 3}">
                        {{ searchResult.remaining_quota }} 次
                    </span>
                </p>
                
                <button 
                    @click="deductQuota" 
                    :disabled="searchResult.remaining_quota < 1" 
                    class="btn deduct-btn"
                >
                    ✅ 服務完成，扣減 1 次額度
                </button>

                <button 
                    @click="handleRenew" 
                    class="btn renew-btn"
                    :class="{'urgent-renew': searchResult.remaining_quota < 1}"
                >
                    💰 會員續約/重新儲值 $3000 (10 次)
                </button>

                <p v-if="searchResult.remaining_quota < 1" class="text-error">額度已用盡，請引導客戶續約。</p>
            </div>

            <div v-else-if="message.includes('查無此會員')" class="result-box warning">
                <p>系統中找不到此會員資料。</p>
                <button @click="goToRegistration" class="btn register-link-btn">
                    點此為客戶辦理 $3000 會員註冊
                </button>
            </div>
        </div>

        <hr>

        <div class="admin-link-section">
            <button @click="goToAdminDashboard" class="btn admin-btn">
                💼 進入後台管理/營收報表
            </button>
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
.tracker-container { max-width: 800px; margin: 20px auto; }
h1, h2 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
hr { border: 0; border-top: 1px solid #ddd; margin: 30px 0; }

/* 訊息和操作員 */
.message { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.message.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

.operator-input { text-align: right; margin-bottom: 15px; }
.operator-input input { width: 150px; padding: 5px; border: 1px solid #ccc; border-radius: 4px; }

/* 按鈕樣式 */
.btn { padding: 12px 18px; border: none; border-radius: 6px; cursor: pointer; color: white; font-weight: bold; transition: background-color 0.3s; }
.btn:hover:not(:disabled) { opacity: 0.9; }
.btn:disabled { background-color: #ccc; cursor: not-allowed; }

/* A. 非會員區塊 */
.non-member-section { background-color: #f4f7f6; }
.service-buttons { display: flex; gap: 20px; margin-top: 15px; }
.service-399 { background-color: #28a745; flex: 1; padding: 20px; font-size: 1.2em; }
.service-499 { background-color: #17a2b8; flex: 1; padding: 20px; font-size: 1.2em; }

/* B. 會員區塊 */
.search-area { display: flex; gap: 10px; margin-top: 15px; }
.search-area input { flex-grow: 1; padding: 10px; font-size: 1em; border: 1px solid #ccc; border-radius: 4px; }
.search-btn { background-color: #007bff; }

.result-box { padding: 20px; border-radius: 8px; margin-top: 20px; }
.result-box.success { background-color: #e2f0e9; color: #155724; }
.quota-display { font-size: 1.4em; font-weight: bold; margin: 10px 0; }
.low-quota { color: #dc3545; /* 警告紅 */ }
.text-error { color: #dc3545; font-weight: bold; margin-top: 5px; }

/* 扣次與續約按鈕 */
.deduct-btn { background-color: #dc3545; margin-top: 15px; width: 100%; }
.renew-btn { background-color: #ff9800; margin-top: 10px; width: 100%; padding: 12px; }
.urgent-renew { background-color: #e91e63; } /* 額度不足時的續約按鈕 */
.register-link-btn { background-color: #ffc107; color: #333; margin-top: 15px; width: 100%; }

/* C. 後台入口 */
.admin-link-section { text-align: center; }
.admin-btn { background-color: #6c757d; width: 100%; padding: 15px; font-size: 1.1em; }
</style>