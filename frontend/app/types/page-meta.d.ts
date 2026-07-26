declare module '#app' {
  interface PageMeta {
    /** Spatie permission name(s); used by `permission` middleware. Admin always passes. */
    permission?: string | string[]
  }
}

export {}
