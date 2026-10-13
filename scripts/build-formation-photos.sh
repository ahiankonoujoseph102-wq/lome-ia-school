#!/usr/bin/env bash
# Photos d'illustration des formations et des offres (Pexels, licence Pexels : usage commercial gratuit).
# Liste : PHOTOS ci-dessous (adresse de la formation, numéro de la photo sur Pexels, point de recadrage),
# la même que App\Support\FormationPhotos::PHOTOS (vérifié par un test). Crédits : CREDITS-PHOTOS.md.
# Produit public/images/photos/formations/{adresse}-{480,800,1200,1600}.webp (16:10, WebP).
# Nécessite curl et ImageMagick (convert). À relancer seulement si une photo change.
set -euo pipefail
cd "$(dirname "$0")/.."
QUALITY="${QUALITY:-70}"
OUT=public/images/photos/formations
TMP="$(mktemp -d)"
mkdir -p "$OUT"
PHOTOS="
chatgpt-claude-initiation-ia 7005397 center
creation-affiches-pro-ia 326502 center
videos-publicitaires-ia 6954098 center
videos-histoire-ia 7971543 center
videos-films-ia 4164088 center
chansons-personnalisees-ia 2531728 north
ebooks-livres-professionnels-ia 3884406 center
site-vitrine-web-ia 7550542 center
sites-web-base-de-donnees-projets-avances 19805876 center
applications-logiciels-ia 9429449 north
creation-saas-ia 30689114 center
creation-agents-ia 30678211 center
automatisation-ia 6744352 center
creer-formations-apprendre-competences-ia 5905751 center
missions-upwork-competences-ia 5198239 center
contenu-tiktok-facebook-youtube-videos-ia 8370484 center
e-commerce-produits-digitaux-ia 5711715 center
formation-gratuite-ia 3769021 center
accompagnement-ia-niveau-1 30688591 center
accompagnement-ia-niveau-2 30688593 center
accompagnement-ia-niveau-3 30688592 center
accompagnement 5749153 center
"
echo "$PHOTOS" | while read -r slug id gravity; do
  [ -z "$slug" ] && continue
  src="$TMP/$slug.jpg"
  curl -sSf -o "$src" "https://images.pexels.com/photos/$id/pexels-photo-$id.jpeg?auto=compress&cs=tinysrgb&w=2000"
  for w in 480 800 1200 1600; do
    h=$((w * 10 / 16))
    convert "$src" -auto-orient -gravity "$gravity" -resize "${w}x${h}^" -extent "${w}x${h}" -strip \
      -define webp:method=6 -quality "$QUALITY" "$OUT/$slug-$w.webp"
  done
  echo "$slug : $(du -ch "$OUT/$slug"-[0-9]*.webp | tail -1 | cut -f1)"
done
rm -rf "$TMP"
