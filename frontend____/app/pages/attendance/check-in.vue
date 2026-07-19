<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'default', middleware: 'auth' })

const token = useAuthTokenCookie()
const headers = { Authorization: `Bearer ${token.value}` }

type CheckInStatus = 'idle' | 'scanning' | 'locating' | 'submitting' | 'success' | 'error'

const status = ref<CheckInStatus>('idle')
const message = ref('')
const result = ref<any>(null)

// QR input (manual fallback or from camera scan)
const qrInput = ref('')
const useManualInput = ref(false)

// Camera QR scanning
const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
let cameraStream: MediaStream | null = null
let scanInterval: ReturnType<typeof setInterval> | null = null

async function startCamera() {
  status.value = 'scanning'
  message.value = ''
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' },
    })
    if (videoRef.value) {
      videoRef.value.srcObject = cameraStream
      await videoRef.value.play()
      scanInterval = setInterval(scanFrame, 300)
    }
  } catch {
    message.value = 'Không thể truy cập camera. Dùng nhập mã thủ công.'
    useManualInput.value = true
    status.value = 'idle'
  }
}

function stopCamera() {
  if (scanInterval) { clearInterval(scanInterval); scanInterval = null }
  if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null }
}

function scanFrame() {
  const video = videoRef.value
  const canvas = canvasRef.value
  if (!video || !canvas || video.readyState < 2) return

  const ctx = canvas.getContext('2d')!
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  ctx.drawImage(video, 0, 0)

  // Use jsQR if available (loaded via CDN)
  const jsQR = (window as any).jsQR
  if (!jsQR) return

  const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height)
  const code = jsQR(imageData.data, imageData.width, imageData.height)
  if (code?.data) {
    stopCamera()
    processQrData(code.data)
  }
}

function processQrData(raw: string) {
  try {
    const parsed = JSON.parse(raw)
    if (parsed.token) {
      qrInput.value = parsed.token
      checkIn()
    } else {
      message.value = 'Mã QR không hợp lệ.'
      status.value = 'error'
    }
  } catch {
    message.value = 'Không thể đọc dữ liệu QR.'
    status.value = 'error'
  }
}

async function checkIn() {
  if (!qrInput.value.trim()) {
    message.value = 'Vui lòng nhập hoặc quét mã QR.'
    return
  }

  status.value = 'locating'
  message.value = 'Đang lấy vị trí GPS...'

  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      status.value = 'submitting'
      message.value = 'Đang điểm danh...'
      try {
        const res = await useApi<any>('/me/attendance/check-in', {
          method: 'POST',
          headers,
          body: {
            qr_token: qrInput.value.trim(),
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
            device_info: navigator.userAgent.substring(0, 200),
          },
        })
        status.value = 'success'
        result.value = res.attendance
        message.value = res.message || 'Điểm danh thành công!'
      } catch (e: any) {
        status.value = 'error'
        message.value = e?.data?.message || 'Điểm danh thất bại.'
        if (e?.data?.distance_meters) {
          message.value += ` (Khoảng cách: ${e.data.distance_meters}m)`
        }
      }
    },
    (err) => {
      status.value = 'error'
      message.value = 'Không thể lấy vị trí. Vui lòng bật GPS và thử lại.'
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

function reset() {
  status.value = 'idle'
  message.value = ''
  result.value = null
  qrInput.value = ''
}

onMounted(() => {
  // Load jsQR from CDN dynamically
  if (!(window as any).jsQR) {
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js'
    document.head.appendChild(script)
  }
})

import { onUnmounted } from 'vue'
onUnmounted(stopCamera)
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md">
      <div class="p-6 border-b text-center">
        <h1 class="text-xl font-bold text-gray-900">Điểm danh QR</h1>
        <p class="text-sm text-gray-500 mt-1">Quét mã QR từ giảng viên để điểm danh</p>
      </div>

      <!-- Success -->
      <div v-if="status === 'success'" class="p-6 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">✅</div>
        <p class="text-lg font-semibold text-green-700 mb-1">{{ message }}</p>
        <div v-if="result" class="bg-gray-50 rounded-xl p-4 text-left text-sm mt-4 space-y-1">
          <p><span class="text-gray-500">Trạng thái:</span>
            <span :class="result.status === 'present' ? 'text-green-600' : 'text-yellow-600'" class="font-medium ml-1">
              {{ result.status === 'present' ? 'Có mặt' : 'Đi muộn' }}
            </span>
          </p>
          <p><span class="text-gray-500">Buổi học:</span> <span class="font-medium">{{ result.offline_session?.title }}</span></p>
          <p><span class="text-gray-500">Địa điểm:</span> <span class="font-medium">{{ result.offline_session?.location }}</span></p>
          <p v-if="result.distance_meters !== null"><span class="text-gray-500">Khoảng cách:</span> <span class="font-medium">{{ result.distance_meters?.toFixed(1) }} m</span></p>
          <p><span class="text-gray-500">Thời gian:</span> <span class="font-medium">{{ new Date(result.checked_in_at).toLocaleTimeString('vi-VN') }}</span></p>
        </div>
        <button class="mt-5 w-full bg-blue-600 text-white rounded-xl py-2.5 text-sm font-medium hover:bg-blue-700" @click="reset">
          Điểm danh buổi khác
        </button>
      </div>

      <!-- Camera scanning -->
      <div v-else-if="status === 'scanning'" class="p-4">
        <div class="relative rounded-xl overflow-hidden bg-black aspect-square">
          <video ref="videoRef" class="w-full h-full object-cover" muted playsinline />
          <canvas ref="canvasRef" class="hidden" />
          <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="w-48 h-48 border-2 border-white rounded-xl opacity-60"></div>
          </div>
        </div>
        <p class="text-center text-sm text-gray-500 mt-3">Hướng camera vào mã QR</p>
        <div class="flex gap-3 mt-4">
          <button class="flex-1 border border-gray-300 rounded-xl py-2.5 text-sm text-gray-700" @click="() => { stopCamera(); status = 'idle' }">
            Huỷ
          </button>
          <button class="flex-1 border border-gray-300 rounded-xl py-2.5 text-sm text-gray-700" @click="() => { stopCamera(); useManualInput = true; status = 'idle' }">
            Nhập thủ công
          </button>
        </div>
      </div>

      <!-- Idle / locating / submitting / error -->
      <div v-else class="p-6 space-y-4">
        <div v-if="status === 'locating' || status === 'submitting'" class="text-center py-6">
          <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-gray-600 text-sm">{{ message }}</p>
        </div>

        <template v-else>
          <div v-if="status === 'error'" class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 text-sm">
            {{ message }}
          </div>

          <!-- Manual input -->
          <div v-if="useManualInput">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mã QR</label>
            <input
              v-model="qrInput"
              placeholder="Dán hoặc nhập token QR..."
              class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
            />
          </div>

          <div class="space-y-3">
            <button
              v-if="!useManualInput"
              class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700 flex items-center justify-center gap-2"
              @click="startCamera"
            >
              <span class="text-lg">📷</span> Quét mã QR
            </button>
            <button
              v-if="useManualInput"
              class="w-full bg-blue-600 text-white rounded-xl py-3 text-sm font-medium hover:bg-blue-700"
              @click="checkIn"
            >
              Điểm danh
            </button>
            <button
              class="w-full border border-gray-300 text-gray-600 rounded-xl py-2.5 text-sm hover:bg-gray-50"
              @click="useManualInput = !useManualInput"
            >
              {{ useManualInput ? 'Dùng camera' : 'Nhập mã thủ công' }}
            </button>
          </div>

          <p class="text-xs text-gray-400 text-center">
            GPS sẽ được dùng để xác minh vị trí. Bạn cần ở trong phạm vi 10m so với phòng học.
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
