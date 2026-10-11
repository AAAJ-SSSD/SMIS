import { describe, expect, it } from 'vitest'
import { http } from '@/api/http'

describe('http client', () => {
  it('uses the session cookie and CSRF header, not bearer tokens', () => {
    expect(http.defaults.withCredentials).toBe(true)
    expect(http.defaults.withXSRFToken).toBe(true)
    expect(http.defaults.xsrfHeaderName).toBe('X-XSRF-TOKEN')
    expect(http.defaults.headers.Authorization).toBeUndefined()
    expect(http.defaults.headers.common?.Authorization).toBeUndefined()
  })
})
