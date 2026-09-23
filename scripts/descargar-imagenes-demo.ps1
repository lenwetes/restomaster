# Descarga imágenes AI de demostración para cada platillo del catálogo MenuSeeder.
# Uso: .\scripts\descargar-imagenes-demo.ps1
# Convención: public/demo/platos/{slug}.jpg se vincula solo vía restomaster:seed-demo.
# Para un plato nuevo basta con agregar su archivo con el slug correspondiente.

$ErrorActionPreference = 'Stop'

$destino = Join-Path (Join-Path (Join-Path $PSScriptRoot '..') 'public') 'demo'
$destino = Join-Path $destino 'platos'
if (-not (Test-Path $destino)) {
    New-Item -ItemType Directory -Path $destino -Force | Out-Null
}

$estiloComida = ', professional food photography, elegant restaurant plating, warm ambient light, appetizing, high detail'
$estiloBebida = ', professional beverage photography, elegant bar presentation, warm ambient light, high detail'

$platos = @(
    @('carpaccio-de-res-trufado', 'thin sliced beef carpaccio with truffle oil and parmesan shavings' + $estiloComida),
    @('ceviche-mixto-de-la-casa', 'mixed fish and shrimp ceviche with leche de tigre' + $estiloComida),
    @('bruschettas-rusticas-3-pzs', 'rustic bruschettas with cherry tomatoes mozzarella and pesto' + $estiloComida),
    @('empanaditas-de-punta-de-anca-4-pzs', 'crispy beef empanadas with chimichurri sauce' + $estiloComida),
    @('bife-de-chorizo-angus-350g', 'grilled Angus sirloin steak marked by the grill on wooden board' + $estiloComida),
    @('ojo-de-bife-ribeye-400g', 'grilled ribeye steak with rosemary' + $estiloComida),
    @('costillas-bbq-ahumadas-500g', 'smoked BBQ pork ribs with glossy glaze' + $estiloComida),
    @('pechuga-gratinada-al-parmesano', 'chicken breast gratin with melted parmesan crust' + $estiloComida),
    @('fettuccine-alfredo-con-pollo-champinones', 'fettuccine alfredo with chicken and mushrooms' + $estiloComida),
    @('raviolis-de-espinaca-ricotta', 'spinach and ricotta ravioli with sage butter' + $estiloComida),
    @('risotto-de-setas-silvestres-trufa', 'wild mushroom and truffle risotto' + $estiloComida),
    @('restomaster-burger-master', 'gourmet master beef burger with fries' + $estiloComida),
    @('hamburguesa-doble-trufa-hongos', 'double burger with truffle and mushrooms' + $estiloComida),
    @('sandwich-de-pulled-pork-braseado', 'pulled pork sandwich with coleslaw' + $estiloComida),
    @('volcan-de-chocolate-fondant', 'warm chocolate fondant volcano cake with vanilla ice cream' + $estiloComida),
    @('cheesecake-clasico-de-frutos-rojos', 'classic cheesecake with red berries topping' + $estiloComida),
    @('tiramisu-tradicional-al-mascarpone', 'traditional tiramisu with cocoa' + $estiloComida),
    @('limonada-de-coco-artesanal', 'creamy coconut lemonade in tall glass' + $estiloBebida),
    @('gin-tonic-citrico-frutos-rojos', 'citrus gin tonic with red berries' + $estiloBebida),
    @('mojito-clasico-de-ron-anejo', 'classic mojito cocktail with mint and lime' + $estiloBebida),
    @('cerveza-artesanal-ipa-330ml', 'craft IPA beer served in glass' + $estiloBebida),
    @('soda-saborizada-maracuya-albahaca', 'passion fruit and basil flavored soda with ice' + $estiloBebida)
)

$fallidas = @()
foreach ($p in $platos) {
    $slug = $p[0]
    $salida = Join-Path $destino ($slug + '.jpg')
    if ((Test-Path $salida) -and (Get-Item $salida).Length -gt 20000) {
        Write-Output ("OK (existe) " + $slug)
        continue
    }
    $prompt = [Uri]::EscapeDataString($p[1])
    $url = "https://image.pollinations.ai/prompt/${prompt}?width=800&height=600&nologo=true&model=flux&seed=7"
    $ok = $false
    for ($i = 1; $i -le 3 -and -not $ok; $i++) {
        try {
            Invoke-WebRequest -Uri $url -OutFile $salida -TimeoutSec 120
            $bytes = [System.IO.File]::ReadAllBytes($salida)[0..3]
            $magia = ($bytes | ForEach-Object { $_.ToString('X2') }) -join ' '
            if ($magia.StartsWith('FF D8 FF') -and (Get-Item $salida).Length -gt 20000) {
                $ok = $true
                Write-Output ("OK " + $slug + " (" + (Get-Item $salida).Length + " bytes)")
            } else {
                Write-Output ("reintento ${i} invalido " + $slug + " (" + $magia + ")")
            }
        } catch {
            Write-Output ("reintento ${i} fallo " + $slug + ": " + $_.Exception.Message)
            Start-Sleep -Seconds 3
        }
    }
    if (-not $ok) { $fallidas += $slug }
}

Write-Output '----------------------------------------'
if ($fallidas.Count -eq 0) {
    Write-Output 'COMPLETO: imagenes validas.'
} else {
    Write-Output ('FALTANTES (' + $fallidas.Count + '): ' + ($fallidas -join ', '))
}
