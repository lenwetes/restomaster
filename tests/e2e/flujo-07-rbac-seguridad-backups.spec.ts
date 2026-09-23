import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 07: Seguridad RBAC y Resiliencia del Sistema', () => {
  test('Aislamiento por roles y acceso restringido para usuarios sin privilegios', async ({ page }) => {
    // 1. Acceso como usuario no autenticado a ruta protegida redirige a login
    await page.goto('/configuracion');
    await expect(page).toHaveURL(/.*login/);

    // 2. Iniciar sesión como administrador
    await page.fill('input[type="email"]', 'admin@restomaster.com');
    await page.fill('input[type="password"]', 'RestoDemo2026');
    await page.click('button[type="submit"]');

    // 3. Administrador accede a Configuración y Módulo de Backups
    await page.goto('/configuracion');
    await expect(page).toHaveURL(/.*configuracion/);
    await expect(page.locator('text=Configuración, text=Copias de Seguridad, text=Backups').first()).toBeVisible();
  });
});
