# plateformweb/it-tools-symfony-bundle

Bundle Symfony 6.4 / 7 pour l'API IT-Tools : le client du SDK `plateformweb/it-tools-php` est configure en YAML, autowirable, et utilise le composant HttpClient de Symfony (profiler, timeouts, `MockHttpClient`).

```bash
composer require plateformweb/it-tools-symfony-bundle
```

Sans Flex, declarer le bundle dans `config/bundles.php` :

```php
Plateformweb\ItToolsBundle\ItToolsBundle::class => ['all' => true],
```

## Configuration

```yaml
# config/packages/it_tools.yaml
it_tools:
    api_key: '%env(IT_TOOLS_API_KEY)%'
    api_secret: '%env(IT_TOOLS_API_SECRET)%'   # cle privee, telle qu'affichee
    # base_uri: 'https://it-tools.plateformweb.com'
    # timeout: 30
    # verify_peer: true                         # false uniquement en developpement local
```

Les cles vont dans `.env.local` ou dans les secrets Symfony, jamais dans le depot.

## Utilisation

```php
use Plateformweb\ItTools\Client;
use Plateformweb\ItTools\File;

final class InvoiceController
{
    public function __construct(private readonly Client $itTools)
    {
    }

    public function compress(string $path): string
    {
        return $this->itTools->pdf()->compress(File::fromPath($path), 'high')->content();
    }
}
```

L'API complete (outils developpeur, PDF, CERFA, QR codes, booklets, kits d'images) est decrite dans le [README du SDK PHP](../php/README.md). Les exceptions `Plateformweb\ItTools\Exception\*` se capturent comme dans le reste du SDK.

## Commande de diagnostic

```bash
php bin/console it-tools:ping
```

Execute un appel reel (1 appel decompte du quota) pour verifier la configuration, la joignabilite et les cles. Code de sortie non nul en cas d'echec ; les cles ne sont jamais affichees.

## Tests

```bash
composer install
vendor/bin/phpunit
```
