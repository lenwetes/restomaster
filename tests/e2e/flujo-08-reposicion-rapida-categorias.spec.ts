import { test, expect } from '@playwright/test';

test.describe('E2E Flujo 08: Modal Rápido de Reposición y Navegación de Categorías en PC', () => {
  test('Flujo completo: Abrir modal de reposición desde Dashboard e interactuar con categorías de Inventario', async ({ page }) => {
    test.setTimeout(90000);

    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
    page.on('response', resp => {
      if (resp.status() >= 400) {
        console.log(`HTTP ${resp.status()} on ${resp.url()}`);
      }
    });

    // 1. Iniciar sesión como Administrador
    await page.goto('/login');
    await expect(page).toHaveURL(/.*login/);

    await page.locator('input[type="email"], input#email').first().fill('admin@restomaster.com');
    await page.locator('input[type="password"], input#password').first().fill('password');
    await page.click('button[type="submit"]');

    // 2. Esperar navegación a Dashboard
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    await expect(page).toHaveURL(/.*dashboard/);
    await expect(page.locator('text=Panel Ejecutivo RestoMaster').first()).toBeVisible();

    // Esperar a que Livewire y Alpine estén completamente inicializados
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // 3. Verificar Alerta Primaria de Inventario y botón Reponer
    const seccionAlertas = page.locator('text=Alerta Primaria de Inventario').first();
    await expect(seccionAlertas).toBeVisible();

    // Si hay insumos en alerta, probar apertura del modal
    const botonReponerDashboard = page.locator('button:has-text("Reponer")').first();
    if (await botonReponerDashboard.isVisible()) {
      await botonReponerDashboard.scrollIntoViewIfNeeded();
      await botonReponerDashboard.click();

      // Verificar que el modal rápido se abre
      const modal = page.locator('text=Reposición de Insumo').first();
      await expect(modal).toBeVisible({ timeout: 15000 });

      // Verificar componentes del modal
      await expect(page.locator('text=Solicitar Pedido al Proveedor').first()).toBeVisible();
      await expect(page.locator('text=Ingreso Inmediato a Stock').first()).toBeVisible();

      // Cambiar a pestaña Ingreso Inmediato
      const tabIngreso = page.locator('button:has-text("Ingreso Inmediato")').first();
      await tabIngreso.click();
      await expect(page.locator('text=Confirmar Ingreso Inmediato al Inventario').first()).toBeVisible({ timeout: 15000 });

      // Probar cálculo instantáneo en tiempo real cambiando la cantidad a 250.0
      const inputCantidad = page.locator('input[x-model="cantidad"]').last();
      await inputCantidad.fill('250.0');
      await expect(page.locator('div[x-show*="ingresar"]').locator('text=$4.000.000 COP').first()).toBeVisible({ timeout: 5000 });

      // Cerrar modal usando tecla Escape
      await page.keyboard.press('Escape');
      await expect(modal).not.toBeVisible({ timeout: 10000 });
    }

    // 3.1. Verificar que las 3 columnas operativas son compactas y paginadas
    const cardInventario = page.locator('text=Alerta Primaria de Inventario').first();
    const cardTopPlatos = page.locator('text=Top Platos Vendidos').first();
    const cardMeseros = page.locator('text=Rendimiento de Meseros').first();
    await expect(cardInventario).toBeVisible();
    await expect(cardTopPlatos).toBeVisible();
    await expect(cardMeseros).toBeVisible();

    // Capturar pantalla de las 3 columnas compactas
    await cardMeseros.scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    await page.screenshot({ path: 'C:/Users/PC-0001/.gemini/antigravity-ide/brain/32168246-85e5-4cf9-a723-08d1476e2332/dashboard_compacto.png' });

    // 4. Navegar a Kardex de Inventario
    await page.goto('/inventario');
    await page.waitForURL('**/inventario', { timeout: 15000 });
    await expect(page).toHaveURL(/.*inventario/);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // 5. Verificar barra de categorías con controles interactivos en PC
    const barraCategorias = page.locator('text=Kardex de Materias Primas').first();
    await expect(barraCategorias).toBeVisible();

    // Verificar pills de categorías
    const catTodas = page.locator('button:has-text("Todas")').first();
    await expect(catTodas).toBeVisible();

    // Si el botón scroll derecho está presente, interactuar con él
    const btnScrollRight = page.locator('button[title="Desplazar hacia la derecha"]').first();
    if (await btnScrollRight.isVisible()) {
      await btnScrollRight.click();
    }

    // Probar click en alguna categoría como Carnes o Lácteos si está visible
    const catCarnes = page.locator('button:has-text("Carnes")').first();
    if (await catCarnes.isVisible()) {
      await catCarnes.click();
    }

    // 6. Verificar botón Reponer en la tabla de Kardex si hay insumo crítico
    const botonReponerInventario = page.locator('button:has-text("Reponer")').first();
    if (await botonReponerInventario.isVisible()) {
      await botonReponerInventario.scrollIntoViewIfNeeded();
      await botonReponerInventario.click();
      const modalKardex = page.locator('text=Reposición de Insumo').first();
      await expect(modalKardex).toBeVisible({ timeout: 15000 });
      await page.keyboard.press('Escape');
      await expect(modalKardex).not.toBeVisible({ timeout: 10000 });
    }
  });
});
