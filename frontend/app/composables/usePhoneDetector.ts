import { FilesetResolver, ObjectDetector } from '@mediapipe/tasks-vision'

let detector: ObjectDetector | null = null
let loadingPromise: Promise<ObjectDetector> | null = null

/**
 * Loads MediaPipe Tasks Vision's ObjectDetector (EfficientDet-Lite0, int8)
 * once per session. Runs on its own WASM runtime (not TensorFlow.js), kept
 * deliberately separate from face-api.js — that library bundles a very old
 * @tensorflow/tfjs-core (1.7.0), which conflicts with modern tfjs-based
 * object-detection models if loaded in the same page. WASM/model assets live
 * in public/models/ (same offline-asset convention as the face-api models),
 * not a CDN.
 */
async function loadDetector(): Promise<ObjectDetector> {
  if (detector) return detector
  if (loadingPromise) return loadingPromise

  loadingPromise = (async () => {
    const wasmFileset = await FilesetResolver.forVisionTasks('/models/wasm')
    detector = await ObjectDetector.createFromOptions(wasmFileset, {
      baseOptions: {
        modelAssetPath: '/models/efficientdet_lite0.tflite',
        delegate: 'CPU',
      },
      runningMode: 'VIDEO',
      scoreThreshold: 0.5,
      maxResults: 3,
      categoryAllowlist: ['cell phone'],
    })
    return detector
  })()

  return loadingPromise
}

export interface PhoneDetectionResult { found: boolean, score: number }

/** Detects a phone in the current video frame — highest-confidence match, if any. */
async function detectPhone(video: HTMLVideoElement): Promise<PhoneDetectionResult> {
  const objectDetector = await loadDetector()
  const result = objectDetector.detectForVideo(video, performance.now())

  let bestScore = 0
  for (const detection of result.detections) {
    for (const category of detection.categories) {
      if (category.categoryName === 'cell phone' && category.score > bestScore) {
        bestScore = category.score
      }
    }
  }

  return { found: bestScore > 0, score: bestScore }
}

export function usePhoneDetector() {
  return {
    loadDetector,
    detectPhone,
  }
}
