/*
 * Mini proxy CORS pour le lecteur IPTV — aucune dépendance, Node.js pur.
 *
 * Beaucoup de serveurs IPTV n'autorisent pas les requêtes venant d'un navigateur
 * (en-têtes CORS absents). Ce proxy se place entre le navigateur et ton serveur
 * IPTV : il relaie les requêtes et ajoute les en-têtes CORS manquants.
 *
 * Lancer :
 *     node iptv-proxy.js
 *
 * Puis, dans iptv.html → « Options avancées », mettre comme préfixe proxy :
 *     http://localhost:8787/
 *
 * Le lecteur enverra alors  http://localhost:8787/http://ton-serveur:8080/...
 * et le proxy transmettra à ton serveur.
 *
 * ⚠️  À n'utiliser que sur ta machine locale. Ne l'expose pas sur Internet :
 *     c'est un proxy ouvert, tes identifiants IPTV transitent par lui.
 */

const http = require("http");
const https = require("https");
const { URL } = require("url");

const PORT = process.env.PORT || 8787;

const server = http.createServer((req, res) => {
  // En-têtes CORS sur toutes les réponses
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "GET,HEAD,OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Range,Content-Type");
  res.setHeader("Access-Control-Expose-Headers", "Content-Length,Content-Range,Accept-Ranges");

  if (req.method === "OPTIONS") { res.writeHead(204).end(); return; }

  // La cible est tout ce qui suit le premier "/"
  const target = req.url.slice(1);
  if (!/^https?:\/\//i.test(target)) {
    res.writeHead(400, { "Content-Type": "text/plain" });
    res.end("Usage : http://localhost:" + PORT + "/http://serveur-iptv:port/...");
    return;
  }

  let upstream;
  try { upstream = new URL(target); }
  catch { res.writeHead(400).end("URL invalide"); return; }

  const client = upstream.protocol === "https:" ? https : http;
  const headers = {};
  // On relaie le Range (essentiel pour le streaming vidéo) et un User-Agent standard
  if (req.headers.range) headers["Range"] = req.headers.range;
  headers["User-Agent"] = req.headers["user-agent"] || "Mozilla/5.0 IPTV-Player";

  const proxied = client.request(
    upstream,
    { method: req.method === "HEAD" ? "HEAD" : "GET", headers },
    (up) => {
      const h = { ...up.headers };
      delete h["access-control-allow-origin"]; // évite les doublons
      res.writeHead(up.statusCode || 502, h);
      up.pipe(res);
    }
  );

  proxied.on("error", (err) => {
    res.writeHead(502, { "Content-Type": "text/plain" });
    res.end("Erreur proxy : " + err.message);
  });

  proxied.end();
});

server.listen(PORT, () => {
  console.log(`Proxy IPTV en écoute sur http://localhost:${PORT}/`);
  console.log(`Dans iptv.html, mets ce préfixe proxy : http://localhost:${PORT}/`);
});
