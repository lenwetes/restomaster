import { defineConfig, devices } from '@playwright/test';

/**
 * Configuración de Playwright E2E para RestoMaster / SushiXpress
 * Soporta emulación táctil móvil (390px mesero/comensal) y pantallas de escritorio/KDS.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
        ['json', { outputFile: 'storage/framework/testing/playwright-report.json' }]
    ],
    use: {
        baseURL: process.env.APP_URL || 'http://localhost:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
    },
    projects: [
        {
            name: 'Mobile Mesero Táctil (iPhone 14)',
            use: {
                ...devices['iPhone 14'],
                hasTouch: true,
            },
        },
        {
            name: 'Tablet KDS / Caja (iPad Air)',
            use: {
                ...devices['iPad Air'],
                hasTouch: true,
            },
        },
        {
            name: 'Desktop Terminal 1440',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
    ],
});
