// jsdom lacks a few browser APIs Vuetify relies on.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

globalThis.visualViewport ??= { width: 1280, height: 800, addEventListener() {}, removeEventListener() {} }
