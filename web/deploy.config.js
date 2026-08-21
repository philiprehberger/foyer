/* eslint-disable @typescript-eslint/no-require-imports */
module.exports = {
  server: {
    host: '44.245.176.5',
    username: 'ubuntu',
    privateKeyPath: require('os').homedir() + '/.ssh/scopeforged_rebuild',
  },
  paths: {
    basePath: '/var/www/foyer-web',
  },
  pm2Process: 'foyer-web',
  filesToTransfer: ['.next', 'public', 'package.json', 'package-lock.json', 'next.config.ts'],
  releasesToKeep: 3,
};
