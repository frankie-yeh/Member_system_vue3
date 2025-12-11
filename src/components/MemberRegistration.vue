<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const API_BASE_URL = 'https://yamay.com.tw/app'; 

const router = useRouter();

// 註冊表單資料狀態
const registrationForm = ref({
    name: '',
    phone: '',
    associated_product_id: 1, // 預設為 1 (標準服務 399)
    operator: 'Admin', // 預設操作員
});
const message = ref('');

// 可選服務項目
const serviceOptions = [
    { id: 1, name: '標準服務 (399)', price: 399.00 },
    { id: 2, name: '進階服務 (499)', price: 499.00 },
];

// ----------------------------------------------------
// A. 處理會員註冊提交
// ----------------------------------------------------
const handleSubmit = async () => {
    message.value = '';

    // 簡單的表單驗證
    if (!registrationForm.value.name || !registrationForm.value.phone || !registrationForm.value.operator) {
        message.value = '⚠️ 姓名、電話和操作員姓名為必填項。';
        return;
    }

    // 確認註冊
    const selectedService = serviceOptions.find(opt => opt.id === registrationForm.value.associated_product_id);
    if (!confirm(`確定要為 ${registrationForm.value.name} 辦理 $3000 會員註冊，並關聯【${selectedService.name}】服務嗎？`)) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/api.php?action=register_member`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(registrationForm.value),
        });
        const data = await response.json();

        if (data.status === 'success') {
            message.value = `✅ ${data.message} 已儲值 10 次額度。`;
            // 清空表單
            registrationForm.value.name = '';
            registrationForm.value.phone = '';
            // 成功後延遲跳轉回主控台
            setTimeout(() => {
                router.push('/');
            }, 3000);
        } else {
            message.value = `❌ 註冊失敗：${data.message}`;
        }
    } catch (error) {
        message.value = '網路錯誤，註冊失敗。';
    }
};

// ----------------------------------------------------
// B. 返回主控台
// ----------------------------------------------------
const goToTracker = () => {
    router.push('/');
};
</script>

<template>
    <div class="registration-container card">
        <h1>👤 會員註冊 (繳費 $3000 / 儲值 10 次)</h1>
        
        <div v-if="message" :class="['message', message.startsWith('✅') ? 'success' : 'error']">
            {{ message }}
        </div>

        <form @submit.prevent="handleSubmit">
            <div class="form-group">
                <label for="name">會員姓名 (必填)：</label>
                <input type="text" id="name" v-model="registrationForm.name" required>
            </div>

            <div class="form-group">
                <label for="phone">會員電話 (必填)：</label>
                <input type="tel" id="phone" v-model="registrationForm.phone" required>
            </div>

            <div class="form-group service-select-group">
                <label>服務方案關聯：</label>
                <div class="radio-options">
                    <label v-for="service in serviceOptions" :key="service.id">
                        <input 
                            type="radio" 
                            :value="service.id" 
                            v-model.number="registrationForm.associated_product_id"
                        >
                        {{ service.name }} (單次收費 ${{ service.price }})
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="operator">操作員姓名 (必填)：</label>
                <input type="text" id="operator" v-model="registrationForm.operator" required>
            </div>
            
            <p class="summary-text">此操作將為會員收費 **$3000**，並給予 **10 次** 服務額度。</p>

            <button type="submit" class="btn submit-btn">
                💰 確認註冊並記錄 $3000 收入
            </button>
            <button type="button" @click="goToTracker" class="btn back-btn">
                返回服務總控台
            </button>
        </form>
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
.registration-container { max-width: 600px; margin: 20px auto; }
h1 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 30px; }

/* 訊息樣式 */
.message { padding: 10px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* 表單樣式 */
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
.form-group input[type="text"],
.form-group input[type="tel"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 1em;
}

/* Radio 選擇樣式 */
.service-select-group {
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background-color: #f9f9f9;
}
.radio-options {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}
.radio-options label {
    font-weight: normal;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.summary-text {
    text-align: center;
    font-size: 1.1em;
    font-weight: bold;
    color: #007bff;
    margin: 30px 0 20px 0;
}

/* 按鈕樣式 */
.btn { 
    width: 100%; 
    padding: 12px 20px; 
    border: none; 
    border-radius: 6px; 
    cursor: pointer; 
    color: white; 
    font-weight: bold; 
    transition: background-color 0.3s;
    margin-bottom: 10px;
}
.submit-btn { 
    background-color: #28a745; /* 綠色 */
    font-size: 1.1em;
}
.submit-btn:hover { background-color: #218838; }

.back-btn {
    background-color: #6c757d; /* 灰色 */
}
.back-btn:hover { background-color: #5a6268; }
</style>