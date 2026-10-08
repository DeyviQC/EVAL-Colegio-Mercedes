import { X509Certificate, createHash } from 'node:crypto';
export function certificateOptions(pem, now=Date.now()) {
  const cert=new X509Certificate(pem);
  if(cert.checkIP('127.0.0.1')!=='127.0.0.1'||Date.parse(cert.validFrom)>now||Date.parse(cert.validTo)<=now)throw new Error('Owned loopback certificate is invalid or expired.');
  const pin=createHash('sha256').update(cert.publicKey.export({format:'der',type:'spki'})).digest('base64');
  return {ignoreHTTPSErrors:false,args:[`--ignore-certificate-errors-spki-list=${pin}`]};
}
