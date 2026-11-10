/**
 * Storefront component & integration test runner (no network / extra dependencies).
 *
 * Usage: `pnpm --filter web test`
 *   1. `tsc -p tsconfig.test.json` compiles the suite to `.test-dist/`
 *   2. this script maps the `@/` alias and stubs `next/*` modules, then runs the suite.
 */
const path = require('path');
const Module = require('module');

const webRoot = path.resolve(__dirname, '..');
const distRoot = path.join(webRoot, '.test-dist', 'apps', 'web', 'src');
const webNodeModules = path.join(webRoot, 'node_modules');

const originalResolveFilename = Module._resolveFilename;
Module._resolveFilename = function (request, parent, isMain, options) {
  if (request.startsWith('@/')) {
    return originalResolveFilename.call(this, path.join(distRoot, request.slice(2)), parent, isMain, options);
  }
  if (request === 'next/link') {
    return require.resolve('./test-mocks/next-link.cjs');
  }
  if (request === 'next/navigation') {
    return require.resolve('./test-mocks/next-navigation.cjs');
  }
  if (request.startsWith('.') || path.isAbsolute(request)) {
    return originalResolveFilename.call(this, request, parent, isMain, options);
  }
  return originalResolveFilename.call(this, request, parent, isMain, {
    ...options,
    paths: [webNodeModules, ...((options && options.paths) || [])],
  });
};

const { runStorefrontTestSuite } = require(path.join(distRoot, '__tests__', 'storefront.test.js'));

runStorefrontTestSuite()
  .then((res) => {
    console.log(`PASS (${res.passed} suites):`);
    for (const name of res.names) {
      console.log(`  ✓ ${name}`);
    }
  })
  .catch((err) => {
    console.error('FAIL:', err);
    process.exit(1);
  });

