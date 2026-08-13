import * as faceapi from 'face-api.js'

let modelsLoaded = false
let loadingPromise: Promise<void> | null = null

/**
 * Loads the (small) face-api.js models once per session. Models live in
 * public/models/ — tiny_face_detector (fast, good enough for periodic
 * in-browser checks) + face_landmark_68 (required for descriptor alignment)
 * + face_recognition (produces the 128-d embedding used for matching).
 */
async function loadModels() {
  if (modelsLoaded) return
  if (loadingPromise) return loadingPromise

  loadingPromise = (async () => {
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
      faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
      faceapi.nets.faceRecognitionNet.loadFromUri('/models'),
    ])
    modelsLoaded = true
  })()

  return loadingPromise
}

function detectorOptions() {
  return new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 })
}

/** Loads a same-origin image URL into an <img> element for face-api to read. */
function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error('image load failed'))
    img.src = src
  })
}

/** Face descriptor (128-d embedding) for the single most prominent face, or null if none found. */
async function descriptorFromElement(el: HTMLImageElement | HTMLVideoElement) {
  const result = await faceapi
    .detectSingleFace(el, detectorOptions())
    .withFaceLandmarks()
    .withFaceDescriptor()
  return result?.descriptor ?? null
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
    descriptorFromImageUrl,
    descriptorFromVideo,
    countFacesInVideo,
    detectFacesWithLandmarks,
    estimateGazeOffset,
    similarityFromDescriptors,
  }
}
