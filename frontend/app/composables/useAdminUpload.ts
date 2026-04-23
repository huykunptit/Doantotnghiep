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
  const token = useAuthTokenCookie()

  async function uploadImage(file: File, folder: 'users' | 'settings' | 'courses', oldPath?: string | null) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('folder', folder)

    if (oldPath) {
      formData.append('old_path', oldPath)
    }

    return await useApi<AdminUploadResponse, FormData>('/admin/upload', {
      method: 'POST',
      body: formData,
      headers: token.value
        ? {
            Authorization: `Bearer ${token.value}`,
          }
        : {},
    })
  }

  return {
    uploadImage,
  }
}

