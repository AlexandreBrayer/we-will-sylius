const path = require('path');
const fs = require('fs');

const Encore = require('@symfony/webpack-encore');

const webpack = require('webpack');

const SyliusAdmin = require('@sylius-ui/admin');
const SyliusShop = require('@sylius-ui/shop');

// Admin config
const adminConfig = SyliusAdmin.getBaseWebpackConfig(path.resolve(__dirname));

// Graphique des ventes du tableau de bord : le script de Sylius (couleurs en dur) est remplacé au build par celui de Yack,
// aux couleurs de la charte. Rien à modifier dans vendor/.
adminConfig.plugins.push(new webpack.NormalModuleReplacementPlugin(
    /[\\/]scripts[\\/]statistics_chart(\.js)?$/,
    path.resolve(__dirname, 'assets/admin/scripts/statistics-chart.js'),
));

// Shop config
const shopConfig = SyliusShop.getBaseWebpackConfig(path.resolve(__dirname));

// Shared controllers
const common_controllers = path.resolve(__dirname, './assets/controllers.json');

// App shop config: Yack stylesheet only (assets/shop/styles/yack.scss), loaded by the SvelteKit front
// (front/src/routes/+layout.svelte, builder canvas). The Twig storefront is gone: no shop JS / Stimulus.
Encore
    .setOutputPath('public/build/app/shop')
    .setPublicPath('/build/app/shop')
    .copyFiles({
      from: './assets/shop/images',
      to: 'images/[path][name].[hash:8].[ext]',
      pattern: /\.(png|jpe?g|gif|svg|webp)$/
    })
    .copyFiles({
      // Lato du design system à une adresse stable (sans empreinte) : le builder (front/, /cms-admin) ne charge pas la feuille
      // de la boutique, il déclare lui-même la police (front/src/lib/admin/admin.css).
      from: './assets/shop/fonts',
      to: 'fonts/[name].[ext]',
      pattern: /\.woff2$/
    })
    .addEntry('app-shop-entry', './assets/shop/entrypoint.js')
    .addAliases({
        '@vendor': path.resolve(__dirname, 'vendor'),
    })
    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSassLoader()
;

const appShopConfig = Encore.getWebpackConfig();

appShopConfig.externals = Object.assign({}, appShopConfig.externals, { window: 'window', document: 'document' });
appShopConfig.name = 'app.shop';

Encore.reset();

// App admin config
Encore
    .setOutputPath('public/build/app/admin')
    .setPublicPath('/build/app/admin')
    .copyFiles({
      from: './assets/admin/images',
      to: 'images/[path][name].[hash:8].[ext]',
      pattern: /\.(png|jpe?g|gif|svg|webp)$/
    })
    .addEntry('app-admin-entry', './assets/admin/entrypoint.js')
    .addAliases({
        '@vendor': path.resolve(__dirname, 'vendor'),
    })
    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSassLoader()
    //.enableStimulusBridge(path.resolve(__dirname, './assets/admin/controllers.json'))
    // remove the following line if you don't want to add automatically controllers provided by plugins
    // You then have to copy them to assets/admin/controllers.json
    .enableStimulusBridge(mergeControllers(
      'admin',
      [common_controllers, path.resolve(__dirname, './assets/admin/controllers.json')]
    ))
;

const appAdminConfig = Encore.getWebpackConfig();

appAdminConfig.externals = Object.assign({}, appAdminConfig.externals, { window: 'window', document: 'document' });
appAdminConfig.name = 'app.admin';

module.exports = [shopConfig, adminConfig, appShopConfig, appAdminConfig];

/**
 * Merge controllers.json from multiple files into one and store in cache
 * Used to merge both controllers.json fed by packages and the one from the app
 */
function mergeControllers(name, filePaths, cacheDir = 'var/cache/webpack') {
  const merged = filePaths.reduce(
    (acc, filePath) => {
      const json = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      acc.controllers  = { ...acc.controllers,  ...json.controllers  };
      acc.entrypoints  = { ...acc.entrypoints,  ...json.entrypoints  };
      return acc;
    },
    { controllers: {}, entrypoints: {} }
  );

  const tmpDir    = path.resolve(__dirname, cacheDir);
  if (!fs.existsSync(tmpDir)) fs.mkdirSync(tmpDir, { recursive: true });

  const outFile = path.join(tmpDir, `controllers.merged.${name}.json`);
  fs.writeFileSync(outFile, JSON.stringify(merged, null, 2), 'utf-8');

  return outFile;
}
