

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const searchQuery = ref('')
const searchResult = ref(null)

const API_BASE_URL = 'https://yamay.com.tw/app'
const router = useRouter()

/* =========================
   狀態
========================= */
const queryMode = ref('day') // day | month
const queryDate = ref(new Date().toISOString().substring(0, 10))
const members = ref([])
const loading = ref(false)
const message = ref('')

/* =========================
   查詢會員
========================= */
const fetchMembers = async () => {
    loading.value = true
    message.value = ''
    members.value = []

    try {
        let url = `${API_BASE_URL}/api.php?action=get_members_by_join_date`

        if (queryMode.value === 'day') {
            url += `&date=${queryDate.value}`
        } else {
            url += `&month=${queryDate.value.substring(0, 7)}`
        }

        const res = await fetch(url)
        const data = await res.json()

        if (data.status !== 'success') {
            message.value = `❌ 查詢失敗：${data.message}`
            return
        }

        members.value = data.data
        message.value = `✅ 查到 ${data.count} 位會員`

    } catch (err) {
        console.error(err)
        message.value = '❌ API 連線失敗'
    } finally {
        loading.value = false
    }
}

const exportCSV = () => {
    const token = localStorage.getItem('admin_token')
    window.open(
        `${API_BASE_URL}/api.php?action=export_members_csv&date=${queryDate.value}&token=${token}`,
        '_blank'
    )
}

const importCSV = async (e) => {
    const file = e.target.files[0]
    if (!file) return

    if (!file.name.toLowerCase().endsWith('.csv')) {
    alert('請上傳 CSV 檔 不是 Excel .xlsx')
    return
}

    const formData = new FormData()
    formData.append('file', file)

    const token = localStorage.getItem('admin_token')

    const res = await fetch(
        `${API_BASE_URL}/api.php?action=import_members_csv`,
        {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${token}`,
            },
            body: formData,
        }
    )

    const data = await res.json()

    if (data.status === 'success') {
        alert(`匯入完成：新增 ${data.inserted}，更新 ${data.updated}`)
        fetchMembers()
    } else {
        alert(data.message)
    }
}


const searchMember = async () => {
    message.value = ''
    searchResult.value = null

    if (!searchQuery.value) {
        message.value = '⚠️ 請輸入會員姓名或電話'
        return
    }

    try {
        const encodedQuery = encodeURIComponent(searchQuery.value)
        const res = await fetch(
            `${API_BASE_URL}/api.php?action=search_member&query=${encodedQuery}`
        )
        const data = await res.json()

        if (data.data) {
            searchResult.value = data.data
            message.value = `✅ 找到會員：${data.data.name}`
            // ⭐ 這一行很重要
            members.value = [data.data]
        } else {
            message.value = '查無此會員'
            members.value = []
        }
    } catch (e) {
        console.error(e)
        message.value = '❌ 查詢失敗'
    }
}


/* =========================
   導航
========================= */
const goBack = () => router.push('/admin')
</script>

<template>
    <div class="card">
        <h1>👥 依加入日期查詢會員</h1>
        <div class="importcsv">
        <button class="btn back" @click="goBack">🔙 返回後台</button>
        <button class="btn export" @click="exportCSV">📥 匯出 CSV</button>
        <label class="btn export csv-btn">📥 匯入 CSV(不是 Excel .xlsx)<input type="file" accept=".csv" @change="importCSV" hidden/></label>

        <div v-if="message" class="message">{{ message }}</div>
        </div>
<hr>
        <div class="query-box">
            <select v-model="queryMode">
                <option value="day">依日期</option>
                <option value="month">依月份</option>
            </select>

            <input
                type="date"
                v-model="queryDate"
            >

            <button class="btn query" @click="fetchMembers" :disabled="loading">
                {{ loading ? '查詢中...' : '查詢會員' }}
            </button>
            <input
        type="text"
        v-model="searchQuery"
        placeholder="輸入會員電話"
        @keyup.enter="searchMember"
    >
    <button class="btn query" @click="searchMember">
        🔍 查詢會員
    </button>
        </div>
<hr>

        <table v-if="members.length" class="member-table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>電話</th>
                    <th>剩餘次數</th>
                    <th>服務方案</th>
                    <th>加入時間</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="m in members" :key="m.id">
                    <td>{{ m.name }}</td>
                    <td>{{ m.phone }}</td>
                    <td>{{ m.remaining_quota }}</td>
                    <td>{{ m.service_name }}</td>
                    <td>{{ m.join_date }}</td>
                </tr>
            </tbody>
        </table>

        <p v-if="!members.length && !loading" class="hint">
            尚無資料
        </p>
    </div>
</template>

<style scoped>
.card {
    background: #fff;
    padding: 30px;
    max-width: 1000px;
    margin: 20px auto;
    border-radius: 10px;
}

h1 {
    margin-bottom: 20px;
}

.importcsv{
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
}


.query-box {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: space-between;
}

select,
input[type="date"] {
    padding: 6px;
}
.csv-btn {
    display: inline-block;
    font-size: 13.33px
}
.label.btn.export.csv-btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;

}


.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: #fff;
}

.btn.query {
    background: #007bff;
}

.btn.back {
    background: #6c757d;
    margin-bottom: 15px;
}

.btn.btn.export{
background-color: darkgreen;
}

.message {
    margin-bottom: 15px;
    font-weight: bold;
}

.member-table {
    width: 100%;
    border-collapse: collapse;
}

.member-table th,
.member-table td {
    border: 1px solid #ddd;
    padding: 8px;
}

.member-table th {
    background: #f1f1f1;
}

.hint {
    color: #999;
    text-align: center;
}
</style>
