#!/bin/bash
# Photos principales des produits (CDN de yack.fr) vers data/yack/images/reel/<code>.<ext>.
# Usage, depuis la racine du dépôt : bash data/yack/images/reel/telecharger.sh
# Les photos ne sont pas versionnées (.gitignore) ; sans photo, l'import prend le visuel de repli.
set -u
cd "$(dirname "$0")"
ok=0; ko=0
curl -fsSL --max-time 30 -o "SRF50ZSX-W.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10018/1ce1d04c_SRF25-50W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRF50ZSX-W"; }
curl -fsSL --max-time 30 -o "SRK20ZS-WFT.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10015/8904916a_SRK-ZS-WFT_titane.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRK20ZS-WFT"; }
curl -fsSL --max-time 30 -o "RCN-K71-E2.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10016/9bbd7f3f_kit-rcn-k71-e2.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec RCN-K71-E2"; }
curl -fsSL --max-time 30 -o "RC-E5.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10025/f27a094c_RC-E5.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec RC-E5"; }
curl -fsSL --max-time 30 -o "LB-TC-5W-E.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10017/b67b2b8d_LB-TC-5W-E_MIS.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec LB-TC-5W-E"; }
curl -fsSL --max-time 30 -o "SRC35ZTL-W.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10020/de7f14af_SRC15-35ZTL-W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRC35ZTL-W"; }
curl -fsSL --max-time 30 -o "SRK25ZS-WFT.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10019/8904916a_SRK-ZS-WFT_titane.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRK25ZS-WFT"; }
curl -fsSL --max-time 30 -o "FDC100VSX-W.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10024/56c9e0e7_FDC100-140VSX-W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec FDC100VSX-W"; }
curl -fsSL --max-time 30 -o "FDTC50VH.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10027/ed1c9429_FDTC25-60VH_close.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec FDTC50VH"; }
curl -fsSL --max-time 30 -o "RC-EX3D.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10026/c7c818b7_RC-EX3D.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec RC-EX3D"; }
curl -fsSL --max-time 30 -o "RCH-E3.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10021/7677c6fc_RCH-E3.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec RCH-E3"; }
curl -fsSL --max-time 30 -o "SRC80ZR-W.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10030/3e9ac338_SRC71-80ZR-W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRC80ZR-W"; }
curl -fsSL --max-time 30 -o "SCM80ZS-W.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10031/255ea2f2_SCM71-80ZS-W.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SCM80ZS-W"; }
curl -fsSL --max-time 30 -o "SCM45ZS-W.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10032/b018364d_SCM30-45ZS-W.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SCM45ZS-W"; }
curl -fsSL --max-time 30 -o "SRK100ZR-W.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10043/482b9fcc_SRK63-100ZR-W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRK100ZR-W"; }
curl -fsSL --max-time 30 -o "SC-ADNA-E.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10047/2ccc2082_mitsubishi-heavy-industries-air-conditioning-sc-adna-e-interface-card-connect-indoor-to-11846-p.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SC-ADNA-E"; }
curl -fsSL --max-time 30 -o "RCN-KIT4-E2.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10053/ee63e3a5_RCN-KIT4-E2.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec RCN-KIT4-E2"; }
curl -fsSL --max-time 30 -o "FDC100VNX-W.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10057/56c9e0e7_FDC100-140VNX-W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec FDC100VNX-W"; }
curl -fsSL --max-time 30 -o "RC-ES1.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10050/eb26d3aa_RC-ES1_face.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec RC-ES1"; }
curl -fsSL --max-time 30 -o "SRC25ZS-W2.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10054/6acaeb75_SRC25-35ZS-W.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRC25ZS-W2"; }
curl -fsSL --max-time 30 -o "SRK25ZS-WF.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10048/d13029b5_SRK20-50ZS-WF.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRK25ZS-WF"; }
curl -fsSL --max-time 30 -o "SRC25ZSP-W1.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10052/de7f14af_SRC25-35ZSP-W1.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SRC25ZSP-W1"; }
curl -fsSL --max-time 30 -o "FDTC25VH1.jpg" "https://cdn.djust-app.com/img/0000000173_yack/product/10058/ed1c9429_FDTC25-60VH_close.jpg" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec FDTC25VH1"; }
curl -fsSL --max-time 30 -o "SCM41ZS-W.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10049/b018364d_SCM30-45ZS-W.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SCM41ZS-W"; }
curl -fsSL --max-time 30 -o "SKM25ZSP-W.png" "https://cdn.djust-app.com/img/0000000173_yack/product/10051/f0ec4e2d_SKM-ZSPW.png" && ok=$((ok+1)) || { ko=$((ko+1)); echo "échec SKM25ZSP-W"; }
echo "photos téléchargées : $ok, échecs : $ko"
