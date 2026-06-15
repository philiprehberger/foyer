/* eslint-disable @typescript-eslint/no-require-imports */
module.exports = {
  server: {
    host: '54.190.150.0',
    username: 'ubuntu',
    privateKeyPath: require('os').homedir() + '/.ssh/ps4_new',
  },
  paths: {
    basePath: '/var/www/foyer-web',
  },
  pm2Process: 'foyer-web',
  filesToTransfer: ['.next', 'public', 'package.json', 'package-lock.json', 'next.config.ts'],
  releasesToKeep: 3,
};
