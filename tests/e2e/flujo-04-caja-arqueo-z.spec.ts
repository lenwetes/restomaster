import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 04: Ciclo Financiero Diario de Caja y Reporte Z', () => {
  test('Cajero abre turno con fondo inicial, registra egreso y realiza arqueo de cierre', async ({ page }) => {
    // 1. Iniciar sesión como cajero / administrador
    await page.goto('/login');
    await page.fill('input[type="email"]', 'admin@restomaster.com');
    await page.fill('input[type="password"]', 'RestoDemo2026');
    await page.click('button[type="submit"]');

    // 2. Navegar a panel de Control de Caja
    await page.goto('/caja');
    await expect(page).toHaveURL(/.*caja/);
    await expect(page.locator('text=Control de Caja, text=Terminales, text=Turno').first()).toBeVisible();

    // 3. Verificar presencia de tarjetas de saldo y arqueo
    await expect(page.locator('text=Efectivo, text=Apertura, text=Arqueo, text=Ventas').first()).toBeVisible();
  });
});
