import sys, random
P=sys.argv[1]
random.seed(12)
angles={
 'pour-vous':{'eyebrow':'Du 1er au 24 décembre','titre':'Le calendrier de l’Avent<br>des joueurs de <span class="p">padel</span>.','sous':'Un vrai objet de padel dans chaque sachet<br>Plus de 130&nbsp;€ de matériel'},
 'cadeau':{'eyebrow':'Idée cadeau de Noël','titre':'Le cadeau de Noël<br>des joueurs de <span class="p">padel</span>.','sous':'24 surprises de padel à ouvrir en décembre<br>Livré avant le 1er&nbsp;décembre'},
}
formats={'carre':(1080,1080),'4x5':(1080,1350),'story':(1080,1920)}
sizes={
 'carre':dict(top=130,ph=470,gap=14,eb=19,t=58,m1=8,m2=10,s=23,m3=16,p=32,ps=18,lg=16),
 '4x5':dict(top=150,ph=660,gap=24,eb=21,t=68,m1=10,m2=12,s=26,m3=22,p=38,ps=21,lg=22),
 'story':dict(top=290,ph=600,gap=24,eb=25,t=76,m1=12,m2=14,s=32,m3=26,p=48,ps=25,lg=20),
}
def garland(w, y0, sag, n):
    # fil + ampoules chaudes avec halo
    pts=[]; s=f'<path d="M-20 {y0} Q{w/2} {y0+sag*2} {w+20} {y0}" fill="none" stroke="#2B2F45" stroke-width="3"/>'
    for i in range(n):
        t=(i+0.5)/n; x=-20+(w+40)*t; y=(1-t)**2*y0+2*(1-t)*t*(y0+sag*2)+t**2*y0
        col=['#FFD27A','#FFB347','#FFE7A8','#FC9A20'][i%4]
        s+=f'<circle cx="{x:.0f}" cy="{y+16:.0f}" r="34" fill="url(#halo)" opacity=".55"/>'
        s+=f'<rect x="{x-5:.0f}" y="{y:.0f}" width="10" height="9" rx="2" fill="#3A3E57"/><ellipse cx="{x:.0f}" cy="{y+18:.0f}" rx="9" ry="12" fill="{col}"/>'
    return s
def snow(w,h,n):
    s=''
    for _ in range(n):
        x=random.uniform(0,w); y=random.uniform(0,h); r=random.choice([1.5,2,2.5,3,4]); o=random.uniform(.25,.8)
        s+=f'<circle cx="{x:.0f}" cy="{y:.0f}" r="{r}" fill="#fff" opacity="{o:.2f}"/>'
    return s
def sprig(x,y,scale,rot,flip=1):
    # branche de sapin stylisée : tige + aiguilles
    g=f'<g transform="translate({x} {y}) rotate({rot}) scale({scale*flip} {scale})">'
    g+='<path d="M0 0 C60 -10 140 -10 230 -40" fill="none" stroke="#5B3A22" stroke-width="5" stroke-linecap="round"/>'
    for i in range(26):
        t=i/25; bx=230*t; by=-40*t*t-8*t
        L=58*(1-t*0.55)
        for side in (-1,1):
            a=side*(55+random.uniform(-8,8))
            import math
            ex=bx+L*math.cos(math.radians(a-15)); ey=by+L*math.sin(math.radians(a-15))*0.9
            col=random.choice(['#1E5B3A','#24704A','#1A4D33','#2F8358'])
            g+=f'<path d="M{bx:.1f} {by:.1f} L{ex:.1f} {ey:.1f}" stroke="{col}" stroke-width="4.5" stroke-linecap="round"/>'
    for (bx,by) in [(70,-28),(150,-48),(195,-30)]:
        g+=f'<circle cx="{bx}" cy="{by}" r="9" fill="#C8102E"/><circle cx="{bx-3}" cy="{by-3}" r="3" fill="#fff" opacity=".5"/>'
    return g+'</g>'
def court(w,h,hz):
    # sol bleu en perspective, lignes blanches, vitres latérales, filet, projecteurs
    cx=w/2; top_w=w*0.34; bot_w=w*1.5; y0=hz; y1=h+40
    def X(t,side): # t : 0 en haut, 1 en bas ; side -1 / +1
        half=top_w/2+(bot_w/2-top_w/2)*t
        return cx+side*half
    s=f'<defs><linearGradient id="sol" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1D4FC4"/><stop offset="1" stop-color="#2F6BFF"/></linearGradient>'
    s+='<linearGradient id="vitre" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9FC0FF" stop-opacity=".22"/><stop offset="1" stop-color="#9FC0FF" stop-opacity=".06"/></linearGradient>'
    s+='<linearGradient id="voile" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#060A2A" stop-opacity="0"/><stop offset=".45" stop-color="#060A2A" stop-opacity=".55"/><stop offset="1" stop-color="#060A2A" stop-opacity=".82"/></linearGradient>'
    s+='<radialGradient id="spot"><stop offset="0" stop-color="#FFF6DA" stop-opacity=".95"/><stop offset="1" stop-color="#FFF6DA" stop-opacity="0"/></radialGradient></defs>'
    # mur du fond (vitre) + sol
    s+=f'<rect x="{X(0,-1):.0f}" y="{y0-h*0.13:.0f}" width="{top_w:.0f}" height="{h*0.13:.0f}" fill="url(#vitre)" stroke="#BFD3FF" stroke-opacity=".35" stroke-width="2"/>'
    s+=f'<path d="M{X(0,-1):.0f} {y0} L{X(0,1):.0f} {y0} L{X(1,1):.0f} {y1} L{X(1,-1):.0f} {y1} Z" fill="url(#sol)"/>'
    # vitres latérales
    for side in (-1,1):
        s+=f'<path d="M{X(0,side):.0f} {y0} L{X(0,side):.0f} {y0-h*0.13:.0f} L{X(1,side)+side*0:.0f} {y1-h*0.75:.0f} L{X(1,side):.0f} {y1} Z" fill="url(#vitre)" stroke="#BFD3FF" stroke-opacity=".3" stroke-width="2"/>'
        for k in range(1,5):
            t=k/5; xa=X(t,side); ya=y0+(y1-y0)*t
            s+=f'<line x1="{xa:.0f}" y1="{ya:.0f}" x2="{xa:.0f}" y2="{ya-(h*0.13+(h*0.75-h*0.13)*t):.0f}" stroke="#C9D7F5" stroke-opacity=".35" stroke-width="3"/>'
    # lignes du terrain
    ln='stroke="#FFFFFF" stroke-opacity=".85" stroke-width="4" fill="none"'
    s+=f'<path d="M{X(0,-1):.0f} {y0} L{X(1,-1):.0f} {y1} M{X(0,1):.0f} {y0} L{X(1,1):.0f} {y1}" {ln}/>'
    for t in (0.18,0.82):
        ya=y0+(y1-y0)*t; s+=f'<line x1="{X(t,-1):.0f}" y1="{ya:.0f}" x2="{X(t,1):.0f}" y2="{ya:.0f}" {ln}/>'
    s+=f'<line x1="{cx:.0f}" y1="{y0+(y1-y0)*0.18:.0f}" x2="{cx:.0f}" y2="{y0+(y1-y0)*0.82:.0f}" {ln}/>'
    # filet
    tn=0.5; yn=y0+(y1-y0)*tn; xl=X(tn,-1); xr=X(tn,1); nh=h*0.06
    s+=f'<rect x="{xl:.0f}" y="{yn-nh:.0f}" width="{xr-xl:.0f}" height="{nh:.0f}" fill="#0A0F33" fill-opacity=".55"/>'
    for i in range(0,int((xr-xl)/14)): s+=f'<line x1="{xl+i*14:.0f}" y1="{yn-nh:.0f}" x2="{xl+i*14:.0f}" y2="{yn:.0f}" stroke="#E8EEFF" stroke-opacity=".25" stroke-width="1"/>'
    s+=f'<line x1="{xl:.0f}" y1="{yn-nh:.0f}" x2="{xr:.0f}" y2="{yn-nh:.0f}" stroke="#FFFFFF" stroke-width="5"/>'
    # projecteurs
    for fx in (X(0,-1)-40, X(0,1)+40):
        s+=f'<circle cx="{fx:.0f}" cy="{y0-h*0.2:.0f}" r="{w*0.16:.0f}" fill="url(#spot)" opacity=".35"/><rect x="{fx-26:.0f}" y="{y0-h*0.2-8:.0f}" width="52" height="16" rx="4" fill="#F7F3E6"/>'
    # voile sombre pour la lisibilité du texte
    s+=f'<rect x="0" y="{y0:.0f}" width="{w}" height="{h-y0+40:.0f}" fill="url(#voile)"/>'
    return s
CSS='''@font-face{font-family:Archivo;src:url(archivo.woff2);font-weight:400 900;font-stretch:62% 125%}
@font-face{font-family:PlexMono;src:url(plexmono.woff2);font-weight:600}
@font-face{font-family:PlexSans;src:url(plexsans.woff2);font-weight:400 700}
*{box-sizing:border-box;margin:0}html,body{width:{w}px;height:{h}px;overflow:hidden;background:#060A2A}
.c{position:relative;width:{w}px;height:{h}px;background:linear-gradient(#060A2A,#0B1140 40%,#060A2A);overflow:hidden}
svg.deco{position:absolute;inset:0;z-index:1}
.in{position:relative;z-index:2;padding-top:{top}px;display:flex;flex-direction:column;align-items:center}
.photo{width:{ph}px;height:{ph}px;border-radius:26px;overflow:hidden;border:6px solid #F5E6C8;box-shadow:0 0 0 2px rgba(245,194,107,.6),0 22px 60px rgba(0,0,0,.55),0 0 80px rgba(255,190,90,.18);flex:none}
.photo img{width:100%;height:100%;object-fit:cover;display:block}
.nc{margin-top:8px;font-family:PlexSans;font-size:15px;color:#8C93B0}
.txt{text-align:center;margin-top:{gap}px;padding:0 50px}
.eyebrow{display:inline-block;font-family:PlexMono;font-weight:600;font-size:{eb}px;letter-spacing:.1em;text-transform:uppercase;color:#060A2A;background:#F5C26B;border-radius:999px;padding:6px 16px}
h1{font-family:Archivo;font-weight:800;font-variation-settings:"wdth" 112;font-size:{t}px;line-height:1;letter-spacing:-.03em;color:#fff;margin:{m1}px 0 {m2}px;text-shadow:0 3px 18px rgba(0,0,0,.7)}
h1 .p{color:#FC9A20}
.sous{font-family:PlexSans;font-size:{s}px;line-height:1.35;color:#EEF1FA;text-shadow:0 2px 10px rgba(0,0,0,.7)}
.prix{display:inline-flex;align-items:center;gap:12px;margin-top:{m3}px;background:#FC9A20;color:#060A2A;border-radius:999px;padding:13px 28px;font-family:Archivo;font-weight:800;font-variation-settings:"wdth" 112;font-size:{p}px;box-shadow:0 8px 30px rgba(252,154,32,.35)}
.prix small{font-family:PlexSans;font-weight:600;font-size:{ps}px}
.logo{margin-top:{lg}px;display:flex;align-items:center;gap:10px;font-family:Archivo;font-weight:800;font-variation-settings:"wdth" 112;font-size:26px;letter-spacing:-.045em;color:#fff}
.logo em{font-style:normal;color:#8AA4FF}.logo span{width:40px;height:40px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center}.logo img{width:34px;height:34px}'''
for a,tx in angles.items():
    for f,(w,h) in formats.items():
        d=dict(sizes[f],w=w,h=h); css=CSS
        for k,v in d.items(): css=css.replace('{'+k+'}',str(v))
        gy={'carre':38,'4x5':44,'story':250}[f]
        deco=f'''<svg class="deco" viewBox="0 0 {w} {h}" width="{w}" height="{h}"><defs><radialGradient id="halo"><stop offset="0" stop-color="#FFD27A" stop-opacity=".9"/><stop offset="1" stop-color="#FFD27A" stop-opacity="0"/></radialGradient></defs>
{court(w,h,{'carre':300,'4x5':360,'story':720}[f])}{snow(w,h,int(w*h/11000))}{garland(w,gy,26,11)}
{sprig(-30,h-40,1.25,-18)}{sprig(w+30,h-40,1.25,-18,-1)}</svg>'''
        html=f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>{css}</style></head><body><div class="c">{deco}<div class="in">
<div class="photo"><img src="calendrier.jpg" alt=""></div><div class="nc">Visuel non contractuel</div>
<div class="txt"><div class="eyebrow">{tx['eyebrow']}</div><h1>{tx['titre']}</h1><div class="sous">{tx['sous']}</div>
<div class="prix">79&nbsp;€ <small>pour les 30 premiers</small></div></div>
<div class="logo"><span><img src="logo-mark-transparent.png" alt=""></span>PADEL<em>OCCAS</em></div></div></div></body></html>'''
        open(f'{P}/noel-{a}-{f}.html','w').write(html)
print('ok')
