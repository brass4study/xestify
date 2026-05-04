/**
 * Xestify - Test Helpers
 * Common utilities for frontend tests to reduce code duplication
 */

let testOutput = null;
let testStats = { passed: 0, failed: 0 };

/**
 * Initialize the test runner with output element and reset counters
 * @param {string} outputElementId - ID of the output element
 * @returns {object} - { passed, failed, finishTestRunner }
 */
export function initTestRunner(outputElementId) {
  testOutput = document.getElementById(outputElementId);
  testStats = { passed: 0, failed: 0 };
  return {
    passed: testStats,
    failed: testStats,
    finishTestRunner: () => finishTestRunner(outputElementId),
  };
}

/**
 * Log a message to the test output
 * @param {string} msg - Message to log
 */
export function log(msg) {
  if (!testOutput) throw new Error('initTestRunner() must be called first');
  testOutput.textContent += msg + '\n';
}

/**
 * Log a separator line (em-dash character)
 * Standard width: 76 characters
 */
export function separator() {
  log('────────────────────────────────────────────────────────────────────────');
}

/**
 * Execute an async test with automatic error handling
 * @param {string} name - Test name
 * @param {function} fn - Async test function
 */
export async function test(name, fn) {
  try {
    await fn();
    log(`  ✅ ${name}`);
    testStats.passed++;
  } catch (e) {
    log(`  ❌ ${name}`);
    log(`     ${e.message}`);
    testStats.failed++;
  }
}

/**
 * Execute a synchronous test with automatic error handling (alias for it)
 * @param {string} name - Test name
 * @param {function} fn - Sync test function that receives container
 */
export function it(name, fn) {
  try {
    fn();
    log(`✅ ${name}`);
    testStats.passed++;
  } catch (e) {
    log(`❌ ${name}`);
    log(`   ${e.message}`);
    testStats.failed++;
  }
}

/**
 * Assert that two values are equal
 * @param {*} actual - Actual value
 * @param {*} expected - Expected value
 * @param {string} msg - Optional message
 */
export function assertEquals(actual, expected, msg = '') {
  const a = JSON.stringify(actual);
  const e = JSON.stringify(expected);
  if (a !== e) {
    throw new Error(`${msg || 'assertEquals'}: expected ${e}, got ${a}`);
  }
}

/**
 * Assert that a value is truthy
 * @param {*} value - Value to test
 * @param {string} msg - Optional message
 */
export function assertTrue(value, msg = 'assertTrue failed') {
  if (!value) throw new Error(msg);
}

/**
 * Create a sandbox element for DOM tests
 * @param {string} sandboxElementId - ID of sandbox container (default: 'sandbox')
 * @returns {HTMLElement} - New div in sandbox
 */
export function makeSandbox(sandboxElementId = 'sandbox') {
  const container = document.getElementById(sandboxElementId);
  if (!container) throw new Error(`Sandbox element #${sandboxElementId} not found`);
  const div = document.createElement('div');
  container.appendChild(div);
  return div;
}

/**
 * Mock fetch with simple status/envelope response
 * Captures request for inspection if needed
 * @param {number} status - HTTP status
 * @param {object} envelope - Response data
 * @param {object} captureRequest - Optional object to capture request details
 */
export function mockFetch(status, envelope, captureRequest = null) {
  globalThis.fetch = async (url, init) => {
    if (captureRequest) {
      captureRequest.url = url;
      captureRequest.init = init;
    }
    return {
      status,
      json: async () => envelope,
    };
  };
}

/**
 * Mock fetch to simulate network error
 */
export function mockFetchNetworkError() {
  globalThis.fetch = async () => {
    throw new TypeError('Failed to fetch');
  };
}

/**
 * Mock fetch with URL map routing
 * Maps URL patterns to response data
 * @param {object} urlMap - { urlPattern: responseData, ... }
 * @returns {function} - Original fetch for restoration
 */
export function mockFetchWithMap(urlMap) {
  const original = globalThis.fetch;
  globalThis.fetch = async (url) => {
    const entries = Object.entries(urlMap).sort(([a], [b]) => b.length - a.length);
    for (const [key, data] of entries) {
      if (url.includes(key)) {
        return {
          status: 200,
          json: async () => ({ ok: true, data }),
        };
      }
    }
    return {
      status: 404,
      json: async () => ({
        ok: false,
        error: { message: 'Not found', code: 404, details: {} },
      }),
    };
  };
  return original;
}

/**
 * Mock fetch with route handlers
 * Similar to urlMap but with more control
 * @param {object} routes - { urlPattern: handler, ... }
 * @returns {function} - Original fetch for restoration
 */
export function mockFetchWithRoutes(routes) {
  const original = globalThis.fetch;
  globalThis.fetch = async (url, init = {}) => {
    const entries = Object.entries(routes).sort(([a], [b]) => b.length - a.length);
    for (const [key, handler] of entries) {
      if (url.includes(key)) {
        const result = await handler(url, init);
        return result;
      }
    }
    return {
      status: 404,
      json: async () => ({
        ok: false,
        error: { message: 'Not found', code: 404, details: {} },
      }),
    };
  };
  return original;
}

/**
 * Finish test run and display summary
 * Colors the result line and renders emojis
 * @param {string} outputElementId - ID of output element
 */
export function finishTestRunner(outputElementId = 'output') {
  const elem = document.getElementById(outputElementId);
  if (!elem) throw new Error(`Output element #${outputElementId} not found`);

  // Add separator and summary
  log('');
  separator();
  const cls = testStats.failed === 0 ? 'pass' : 'fail';
  log(`Resultado: ${testStats.passed} passed, ${testStats.failed} failed`);

  // Apply HTML styling
  elem.innerHTML = elem.textContent
    .replaceAll('✅', '<span class="pass">✅</span>')
    .replaceAll('❌', '<span class="fail">❌</span>')
    .replace(/Resultado:.*/, (m) => `<span class="${cls}">${m}</span>`);
}
