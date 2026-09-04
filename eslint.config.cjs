/* eslint-env node */
const js = require('@eslint/js');
const vuePlugin = require('eslint-plugin-vue');
const tsPlugin = require('@typescript-eslint/eslint-plugin');
const tsParser = require('@typescript-eslint/parser');
const vueParser = require('vue-eslint-parser');
const prettier = require('eslint-plugin-prettier');
const globals = require('globals');

const vueConfig = (vuePlugin.configs && vuePlugin.configs['vue3-recommended']) || {};
const tsConfig = (tsPlugin.configs && tsPlugin.configs.recommended) || {};

module.exports = [
  {
    ignores: [
      '**/vendor/**',
      '**/storage/**',
      '**/bootstrap/**',
      '**/public/**',
      '**/node_modules/**',
    ],
  },
  {
    files: ['resources/js/**/*.{js,ts,vue}'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        parser: tsParser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
      globals: {
        ...globals.browser,
        ...globals.es2021,
      },
    },
    plugins: {
      vue: vuePlugin,
      '@typescript-eslint': tsPlugin,
      prettier,
    },
    rules: {
      ...js.configs.recommended.rules,
      ...(vueConfig.rules || {}),
      ...(tsConfig.rules || {}),
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
      'prettier/prettier': 'error',
    },
  },
];
