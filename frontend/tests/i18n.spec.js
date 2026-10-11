import { afterEach, describe, expect, it } from 'vitest'
import { applyLocale, i18n } from '@/i18n'
import ar from '@/i18n/ar'
import en from '@/i18n/en'
import { makeVuetify } from '@/plugins/vuetify'

function keys(obj, prefix = '') {
  return Object.entries(obj).flatMap(([k, v]) =>
    typeof v === 'object' ? keys(v, `${prefix}${k}.`) : [`${prefix}${k}`])
}

describe('i18n', () => {
  afterEach(() => applyLocale('ar'))

  it('defaults to Arabic', () => {
    expect(i18n.global.locale.value).toBe('ar')
  })

  it('has the same keys in Arabic and English', () => {
    expect(keys(ar).sort()).toEqual(keys(en).sort())
  })

  it('switches the document to RTL for Arabic and LTR for English', () => {
    const vuetify = makeVuetify()

    applyLocale('en', vuetify)
    expect(document.documentElement.getAttribute('dir')).toBe('ltr')
    expect(document.documentElement.getAttribute('lang')).toBe('en')
    expect(vuetify.locale.isRtl.value).toBe(false)

    applyLocale('ar', vuetify)
    expect(document.documentElement.getAttribute('dir')).toBe('rtl')
    expect(vuetify.locale.isRtl.value).toBe(true)
    expect(i18n.global.t('nav.users')).toBe('المستخدمون')
  })

  it('ignores unsupported locales', () => {
    applyLocale('fr')
    expect(i18n.global.locale.value).toBe('ar')
  })
})
