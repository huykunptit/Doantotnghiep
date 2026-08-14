export interface AdminUploadResponse {
  message: string
  path: string
  url: string
  meta?: {
    name?: string
    size?: number
    mime?: string
    disk?: string
  }
}

export function useAdminUpload() {
  async function uploadImage(
    file: File,
    folder: 'users' | 'settings' | 'courses' | 'faces',
    oldPath?: string | null,
    endpoint = '/auth/upload',
  ) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('folder', folder)
    if (oldPath) formData.append('old_path', oldPath)

    return await useApi<AdminUploadResponse, FormData>(endpoint, {
      method: 'POST',
      body: formData,
    })
  }

  return { uploadImage }
}
