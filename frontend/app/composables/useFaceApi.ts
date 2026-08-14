import * as faceapi from 'face-api.js'

let detectionModelsLoaded = false
let detectionLoadingPromise: Promise<void> | null = null
let recognitionModelLoaded = false
let recognitionLoadingPromise: Promise<void> | null = null

/**
 * Loads only the small detector models (~560KB) — enough to show a live
 * preview and enable the capture button fast. The much heavier recognition
 * model (~6.4MB, only needed to turn a captured frame into a descriptor)
 * loads separately so it never blocks the camera UI from becoming usable.
 */
async function loadDetectionModels() {
  if (detectionModelsLoaded) return
  if (detectionLoadingPromise) return detectionLoadingPromise

  detectionLoadingPromise = (async () => {
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
      faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
    ])
    detectionModelsLoaded = true
  })()

  return detectionLoadingPromise
}

async function loadRecognitionModel() {
  if (recognitionModelLoaded) return
  if (recognitionLoadingPromise) return recognitionLoadingPromise

  recognitionLoadingPromise = (async () => {
    await faceapi.nets.faceRecognitionNet.loadFromUri('/models')
    recognitionModelLoaded = true
  })()

  return recognitionLoadingPromise
}

/** Loads every model needed to actually compute a face descriptor. */
async function loadModels() {
  await Promise.all([loadDetectionModels(), loadRecognitionModel()])
}

function detectorOptions(inputSize = 416, scoreThreshold = 0.3) {
  return new faceapi.TinyFaceDetectorOptions({ inputSize, scoreThreshold })
}

/** Loads an image as a blob URL so face-api can read pixels without CORS tainting. */
async function loadImage(src: string): Promise<HTMLImageElement> {
  const response = await fetch(src, { credentials: 'same-origin', cache: 'no-store' })
  if (!response.ok) throw new Error('image load failed')
  const blob = await response.blob()
  const objectUrl = URL.createObjectURL(blob)

  try {
    const img = await new Promise<HTMLImageElement>((resolve, reject) => {
      const el = new Image()
      el.onload = () => resolve(el)
      el.onerror = () => reject(new Error('image load failed'))
      el.src = objectUrl
    })

    // Copy onto a canvas before revoking the blob URL (some browsers clear img pixels).
    const canvas = document.createElement('canvas')
    canvas.width = img.naturalWidth || img.width
    canvas.height = img.naturalHeight || img.height
    const ctx = canvas.getContext('2d')
    if (!ctx || !canvas.width || !canvas.height) return img
    ctx.drawImage(img, 0, 0)

    const baked = await new Promise<HTMLImageElement>((resolve, reject) => {
      const el = new Image()
      el.onload = () => resolve(el)
      el.onerror = () => reject(new Error('image bake failed'))
      el.src = canvas.toDataURL('image/jpeg', 0.92)
    })
    return baked
  }
  finally {
    URL.revokeObjectURL(objectUrl)
  }
}

function upscaleForDetection(source: HTMLImageElement | HTMLVideoElement | HTMLCanvasElement) {
  const width = 'videoWidth' in source ? source.videoWidth || source.width : source.width
  const height = 'videoHeight' in source ? source.videoHeight || source.height : source.height
  if (!width || !height) return source
  if (width >= 320 && height >= 320) return source

  const scale = Math.max(320 / width, 320 / height)
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(width * scale)
  canvas.height = Math.round(height * scale)
  const ctx = canvas.getContext('2d')
  if (!ctx) return source
  ctx.imageSmoothingEnabled = true
  ctx.drawImage(source as CanvasImageSource, 0, 0, canvas.width, canvas.height)
  return canvas
}

/** Face descriptor (128-d embedding) for the single most prominent face, or null if none found. */
async function descriptorFromElement(el: HTMLImageElement | HTMLVideoElement | HTMLCanvasElement) {
  const input = upscaleForDetection(el)
  const attempts = [
    detectorOptions(416, 0.3),
    detectorOptions(320, 0.25),
    detectorOptions(512, 0.2),
  ]

  for (const options of attempts) {
    const result = await faceapi
      .detectSingleFace(input, options)
      .withFaceLandmarks()
      .withFaceDescriptor()
    if (result?.descriptor) return result.descriptor
  }

  return null
}

async function descriptorFromImageUrl(url: string) {
  await loadModels()
  const img = await loadImage(url)
  return descriptorFromElement(img)
}

async function descriptorFromVideo(video: HTMLVideoElement) {
  await loadModels()
  return descriptorFromElement(video)
}

/** Counts detected faces in a video frame — used for continuous no_face/multiple_faces monitoring. */
async function countFacesInVideo(video: HTMLVideoElement) {
  await loadModels()
  const results = await faceapi.detectAllFaces(video, detectorOptions())
  return results.length
}

export interface GazeOffset { yaw: number, pitch: number }

/**
 * Detects all faces plus their 68-point landmarks in one pass — used by the
 * continuous monitor so it only runs the detector once per tick instead of
 * once for the face count and again for head-pose landmarks.
 */
async function detectFacesWithLandmarks(video: HTMLVideoElement) {
  await loadModels()
  return faceapi.detectAllFaces(video, detectorOptions()).withFaceLandmarks()
}

/**
 * Rough 2D head-pose estimate from 68-point landmarks — not true 3D pose (no
 * camera intrinsics available), just enough to catch a face that has turned
 * or tilted away from a known-good baseline pose:
 *  - yaw: how far the nose tip sits off-center between the outer eye
 *    corners, normalized by face width. 0 = centered, larger = turned
 *    left/right.
 *  - pitch: how far the nose tip sits from the eye line, normalized by
 *    eye-to-chin distance. Smaller = head tipped down (e.g. looking at a
 *    phone in the lap), larger = tipped up.
 */
function estimateGazeOffset(landmarks: faceapi.FaceLandmarks68): GazeOffset | null {
  const points = landmarks.positions
  const leftEyeOuter = points[36]
  const rightEyeOuter = points[45]
  const noseTip = points[30]
  const chin = points[8]
  if (!leftEyeOuter || !rightEyeOuter || !noseTip || !chin) return null

  const faceWidth = Math.abs(rightEyeOuter.x - leftEyeOuter.x)
  if (faceWidth < 1) return null

  const eyeMidX = (leftEyeOuter.x + rightEyeOuter.x) / 2
  const eyeMidY = (leftEyeOuter.y + rightEyeOuter.y) / 2
  const faceHeight = Math.abs(chin.y - eyeMidY)
  if (faceHeight < 1) return null

  return {
    yaw: (noseTip.x - eyeMidX) / faceWidth,
    pitch: (noseTip.y - eyeMidY) / faceHeight,
  }
}

/** face-api.js FaceRecognitionNet euclidean distance -> a 0..1 similarity score (1 = identical). */
function similarityFromDescriptors(a: Float32Array, b: Float32Array) {
  const distance = faceapi.euclideanDistance(a, b)
  return Math.max(0, Math.min(1, 1 - distance))
}

export function useFaceApi() {
  return {
    loadModels,
    loadDetectionModels,
    descriptorFromImageUrl,
    descriptorFromVideo,
    countFacesInVideo,
    detectFacesWithLandmarks,
    estimateGazeOffset,
    similarityFromDescriptors,
  }
}
