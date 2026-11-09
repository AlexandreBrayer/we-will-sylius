#!/bin/bash
# Photos principales des produits (CDN de yack.fr) vers <dossier>/<code>.<ext> : seules les photos absentes sont
# téléchargées, chacune avec un délai court et quelques nouvelles tentatives, le tout dans une durée bornée.
# Usage, depuis la racine du dépôt : bash data/yack/images/reel/telecharger.sh [dossier]
#   dossier : destination, par défaut celui du script ; sur Railway, le volume (docker/railway/photos.sh).
# Les photos ne sont pas versionnées (.gitignore) ; sans photo, l'import prend le visuel de repli.
# Réglages (variables d'environnement, valeur par défaut entre parenthèses) :
#   YACK_PHOTOS_SOURCE   adresse de base du CDN
#   YACK_PHOTOS_DELAI    durée maximale d'un essai, en secondes (20)
#   YACK_PHOTOS_ESSAIS   essais par photo (3)
#   YACK_PHOTOS_BUDGET   durée totale maximale, en secondes (600) ; au-delà, les photos restantes sont « non tentées »
#   YACK_PHOTOS_ABANDON  échecs d'affilée au bout desquels le CDN est jugé injoignable ou bloqué et le passage s'arrête (5) ;
#                        une photo absente du CDN (404, 410) prouve qu'il répond et remet ce compte à zéro
# Dernière ligne : « photos présentes : N, téléchargées : N, échecs : N, non tentées : N (sur N, en N s) ».
# Code de sortie : 0 quand plus aucune photo ne manque, 1 sinon.
set -u
export LC_ALL=C

source_url=${YACK_PHOTOS_SOURCE:-https://cdn.djust-app.com/img/0000000173_yack/product}
delai=${YACK_PHOTOS_DELAI:-20}
essais=${YACK_PHOTOS_ESSAIS:-3}
budget=${YACK_PHOTOS_BUDGET:-600}
abandon=${YACK_PHOTOS_ABANDON:-5}
dest=${1:-$(dirname "$0")}

if ! mkdir -p "$dest" || [ ! -w "$dest" ]; then
    echo "photos : dossier $dest impossible à créer ou à écrire" >&2
    exit 1
fi
trap 'rm -f "$dest"/.*.part' EXIT
trap 'exit 130' INT TERM

presentes=0
telechargees=0
echecs=0
non_tentees=0
total=0
affilee=0
arret=""
debut=$SECONDS
cause=""

# Un essai : la réponse va dans un fichier temporaire, gardé seulement si c'est une image (JPEG, PNG, WebP ou GIF).
# Renvoie 0 (photo en place), 1 (échec à retenter), 2 (refus du CDN, inutile de retenter) ou 3 (photo absente du CDN) ;
# la cause est dans $cause.
essai() {
    local fichier=$1 chemin=$2 duree=$3 part="$dest/.$1.part" code statut signature
    code=$(curl -sS -L --connect-timeout 5 --max-time "$duree" -o "$part" -w '%{http_code}' "$source_url/$chemin" 2>/dev/null)
    statut=$?
    if [ "$statut" -ne 0 ]; then
        rm -f "$part"
        case "$statut" in
            6) cause="nom du CDN introuvable (curl 6)" ;;
            7) cause="connexion refusée (curl 7)" ;;
            28) cause="délai dépassé (curl 28)" ;;
            *) cause="erreur réseau (curl $statut, HTTP ${code:-000})" ;;
        esac
        return 1
    fi
    if [ "$code" != "200" ]; then
        rm -f "$part"
        case "$code" in
            404 | 410) cause="HTTP $code (photo absente du CDN)"; return 3 ;;
            401 | 403) cause="HTTP $code (accès refusé par le CDN ou le réseau)"; return 2 ;;
            408 | 429) cause="HTTP $code"; return 1 ;;
            4??) cause="HTTP $code"; return 2 ;;
            *) cause="HTTP $code"; return 1 ;;
        esac
    fi
    signature=$(head -c 4 "$part" 2>/dev/null | tr -d '\000')
    case "$signature" in
        $'\xff\xd8\xff'* | $'\x89PNG' | RIFF | GIF8)
            mv -f "$part" "$dest/$fichier"
            return 0
            ;;
    esac
    rm -f "$part"
    cause="réponse qui n'est pas une image"
    return 1
}

restant() {
    if [ "$budget" -le 0 ]; then
        echo "$delai"
    else
        echo $((budget - (SECONDS - debut)))
    fi
}

echo "photos : source $source_url, destination $dest"
while read -r fichier chemin; do
    total=$((total + 1))
    if [ -s "$dest/$fichier" ]; then
        presentes=$((presentes + 1))
        continue
    fi
    if [ -z "$arret" ] && [ "$(restant)" -le 0 ]; then
        arret="durée maximale atteinte ($budget s)"
    fi
    if [ -n "$arret" ]; then
        non_tentees=$((non_tentees + 1))
        continue
    fi

    n=1
    while :; do
        duree=$(restant)
        [ "$duree" -gt "$delai" ] && duree=$delai
        # curl --max-time 0 ne borne rien : au moins 1 s, même quand le budget vient d'être atteint
        [ "$duree" -lt 1 ] && duree=1
        essai "$fichier" "$chemin" "$duree"
        resultat=$?
        if [ "$resultat" -ne 1 ] || [ "$n" -ge "$essais" ] || [ "$(restant)" -le 2 ]; then
            break
        fi
        n=$((n + 1))
        sleep 2
    done

    if [ "$resultat" -eq 0 ]; then
        telechargees=$((telechargees + 1))
        affilee=0
    else
        echecs=$((echecs + 1))
        if [ "$resultat" -eq 3 ]; then
            affilee=0
        else
            affilee=$((affilee + 1))
        fi
        echo "échec ${fichier%.*} : $cause"
        if [ "$affilee" -ge "$abandon" ]; then
            arret="CDN injoignable ou bloqué ($abandon échecs d'affilée)"
        fi
    fi
done <<'LISTE'
SRF50ZSX-W.jpg 10018/1ce1d04c_SRF25-50W.jpg
SRK20ZS-WFT.png 10015/8904916a_SRK-ZS-WFT_titane.png
RCN-K71-E2.jpg 10016/9bbd7f3f_kit-rcn-k71-e2.jpg
RC-E5.jpg 10025/f27a094c_RC-E5.jpg
LB-TC-5W-E.png 10017/b67b2b8d_LB-TC-5W-E_MIS.png
SRC35ZTL-W.jpg 10020/de7f14af_SRC15-35ZTL-W.jpg
SRK25ZS-WFT.png 10019/8904916a_SRK-ZS-WFT_titane.png
FDC100VSX-W.jpg 10024/56c9e0e7_FDC100-140VSX-W.jpg
FDTC50VH.jpg 10027/ed1c9429_FDTC25-60VH_close.jpg
RC-EX3D.jpg 10026/c7c818b7_RC-EX3D.jpg
RCH-E3.jpg 10021/7677c6fc_RCH-E3.jpg
SRC80ZR-W.jpg 10030/3e9ac338_SRC71-80ZR-W.jpg
SCM80ZS-W.png 10031/255ea2f2_SCM71-80ZS-W.png
SCM45ZS-W.png 10032/b018364d_SCM30-45ZS-W.png
SRK100ZR-W.jpg 10043/482b9fcc_SRK63-100ZR-W.jpg
SC-ADNA-E.png 10047/2ccc2082_mitsubishi-heavy-industries-air-conditioning-sc-adna-e-interface-card-connect-indoor-to-11846-p.png
RCN-KIT4-E2.jpg 10053/ee63e3a5_RCN-KIT4-E2.jpg
FDC100VNX-W.jpg 10057/56c9e0e7_FDC100-140VNX-W.jpg
RC-ES1.png 10050/eb26d3aa_RC-ES1_face.png
SRC25ZS-W2.jpg 10054/6acaeb75_SRC25-35ZS-W.jpg
SRK25ZS-WF.jpg 10048/d13029b5_SRK20-50ZS-WF.jpg
SRC25ZSP-W1.jpg 10052/de7f14af_SRC25-35ZSP-W1.jpg
FDTC25VH1.jpg 10058/ed1c9429_FDTC25-60VH_close.jpg
SCM41ZS-W.png 10049/b018364d_SCM30-45ZS-W.png
SKM25ZSP-W.png 10051/f0ec4e2d_SKM-ZSPW.png
SKM15ZSP-W.png 10055/f0ec4e2d_SKM-ZSPW.png
SRK20ZS-W.jpg 10056/fefc97ae_SRK20-50ZS-W.jpg
SRC20ZSX-W.jpg 10059/9b78a485_SRC40-60ZSX-W.jpg
SRK20ZSX-WF.png 10078/6015d76f_SRK20-60ZSX-WF.png
SRK25ZS-W.jpg 10077/fefc97ae_SRK20-50ZS-W.jpg
SRK25ZTL-W.png 10081/d6aafded_SRK15-50ZTL-W.png
DIS-WA1G.png 10084/0470511a_DIS-WA1G-9bab6e7b.png
SRK35ZS-W.jpg 10079/fefc97ae_SRK20-50ZS-W.jpg
SRR50ZS-W.jpg 10093/5778b014_SRR50-60ZS-W.jpg
FDTC60VH.jpg 10087/ed1c9429_FDTC25-60VH_close.jpg
SCM30ZS-W.png 10080/b018364d_SCM30-45ZS-W.png
SKM35ZSP-W.png 10091/f0ec4e2d_SKM-ZSPW.png
SRK20ZS-WT.png 10086/9bdf1f74_SRK20-50ZS-WT.png
SRK50ZS-WFT.png 10092/8904916a_SRK-ZS-WFT_titane.png
SRC35ZSX-W.jpg 10090/9b78a485_SRC40-60ZSX-W.jpg
YORABTM200.png 10095/e26c861a_YORABTM200.png
SRC25ZTL-W.jpg 10097/de7f14af_SRC15-35ZTL-W.jpg
SRF35ZS-W.jpg 10098/1ce1d04c_SRF25-50W.jpg
FDUM50VH.jpg 10096/a1116a0b_FDUM100-140VH.jpg
SRF25ZS-W.jpg 10099/1ce1d04c_SRF25-50W.jpg
SC-THB-E3.png 10102/6defd5a2_SC-THB-E3.png
SRK25ZSX-WF.png 10101/6015d76f_SRK20-60ZSX-WF.png
T-PSA-5CW-E.png 10111/11503930_T-PSA-5CW(B)-E.png
SKM20ZSP-W.png 10114/f0ec4e2d_SKM-ZSPW.png
SCM71ZS-W.png 10116/255ea2f2_SCM71-80ZS-W.png
YORABTM450.png 10112/73a680da_YORABTM450.png
FDC71VNX-W.jpg 10115/64c92e5b_FDC71VNX-W.jpg
YPSR-SR50-1.jpg 10117/6887b205_Plenum%20soufflage%20-%20reprise.jpg
SRC40ZSX-W1.jpg 10132/9b78a485_SRC40-60ZSX-W1.jpg
SRC20ZS-W.jpg 10133/6acaeb75_SRC25-35ZS-W.jpg
SRK25ZS-WT.png 10138/9bdf1f74_SRK20-50ZS-WT.png
FDTC40VH.jpg 10143/ed1c9429_FDTC25-60VH_close.jpg
SRK20ZTL-W.png 10156/d6aafded_SRK15-50ZTL-W.png
EZH-ZPV.png 11254/95468f83_PLENUM%20VMC%20EZH-ZPV%20YACK.png
DOS-2A-4.png 10144/713abe5b_DOS-2A-4.png
SRK50ZSX-WF.png 10157/6015d76f_SRK20-60ZSX-WF.png
SRK15ZS-WF.jpg 10153/d13029b5_SRK20-50ZS-WF.jpg
RC-EX3A.png 10148/dff4847b_RC-EX3A.png
ECOZR.png 11246/d614bd67_ECOZR-Z-ZT.png
SRK20ZS-WF.jpg 10161/d13029b5_SRK20-50ZS-WF.jpg
SCM100ZS-W.png 10158/58c43a00_SCM100ZS-W.png
SRC20ZTL-W.jpg 10152/de7f14af_SRC15-35ZTL-W.jpg
SRK71ZTL-W.png 10154/08a22881_SRK63-71ZTL-W-1.png
SC-GIFN-E.png 10151/b176231e_SC-GIF%20schema%202019.png
SCM60ZS-W.png 10162/596a3cc1_SCM50-60ZS-W.png
FDC200VSA-W.png 10436/43d0149f_FDC200-280VSA-W.png
T-PSAE-5BW-E.png 10160/f5de3bb0_T-PSAE-5BW-E_FDT-VH_Blanc_Optiflux.png
SRC50ZTL-W.jpg 10164/6acaeb75_SRC50ZTL-W.jpg
RCN-K-E2.jpg 10163/a0e6dd22_rcn-k-e2.jpg
EZH-ZP.png 11256/55fd6c95_PLENUM%20EZH-ZP%20YACK.png
TC-PSAE-5AW-E.png 10185/540ec91a_TC-PSA-5AW-E_Optiflux.png
FDC121KXZEN1-W.jpg 10186/b32e58c2_FDC121-155KXZEN(S)1-W.jpg
SRK50ZSP-W1.jpg 10192/e44ae349_SRK25-50ZSP-W1.jpg
SRK60ZSX-W.jpg 10184/4519774f_SRK20-60ZSX-W.jpg
RCN-T-5BW-E2.jpg 10199/b5ea6ff6_RCN-T-5BW-E2.jpg
SRC35ZSP-W1.jpg 10193/de7f14af_SRC25-35ZSP-W1.jpg
DIS-TA1G.png 10189/93611b06_Mitsubishi-DIS-TA1G.png
SC-BIKN2-E.png 10198/772f6a18_Kit_SC-BIKN2-E.png
UM-FL1EF.jpg 10200/2705628a_FDUM-filter.jpg
SRC15ZTL-W.jpg 10210/de7f14af_SRC15-35ZTL-W.jpg
SRC35ZS-W2.jpg 10204/6acaeb75_SRC25-35ZS-W.jpg
SRC50ZSX-W3.jpg 10202/d24d0050_SRC50-60ZSX-W3.jpg
YGSAOPH250.png 10203/473aa78f_Gaine.png
TC-PSA-5AW-E.jpg 10207/c1166f1b_TC-PSA-5AW-E.jpg
SRC25ZSX-W.jpg 10208/9b78a485_SRC40-60ZSX-W.jpg
SRC60ZSX-W3.jpg 10206/d24d0050_SRC50-60ZSX-W3.jpg
SRK35ZS-WF.jpg 10209/d13029b5_SRK20-50ZS-WF.jpg
SCM40ZS-W.png 10212/b018364d_SCM30-45ZS-W.png
SRK15ZS-WFT.png 10211/8904916a_SRK-ZS-WFT_titane.png
SRC45ZSP-W1.jpg 10220/2bec42d6_SRC45-50ZSP-W1.jpg
CW-H-E1.jpg 10227/fb700b0a_set%20final%20version.jpg
SRK20ZSX-W.jpg 10224/4519774f_SRK20-60ZSX-W.jpg
SRK35ZS-WT.png 10226/9bdf1f74_SRK20-50ZS-WT.png
ECOZF.png 11247/0a2ddfac_ECOZF-Z-ZT.png
ECOZFS.png 11250/056a9c50_ECOZFS-Z-ZT.png
DIS-WB1G.png 10234/78ffc8c5_DIS-WB1G-906077bb.png
WF-RAC.jpg 10239/38b1ac7d_WiFi%20Control.jpg
ECOZK.png 11173/19cdc9b2_ECOZKRR1-3%20-%20plenum.png
ECOZRS.png 11249/3767088f_ECOZRS-Z-ZT.png
EZH-PDH.png 11248/1e292955_PLENUMKIT%20DOUBLE%20HAUTEUR.png
SRR35ZS-W.jpg 10251/fe7ba1c6_SRR25-35ZS-W.jpg
SRK50ZSX-W.jpg 10249/4519774f_SRK20-60ZSX-W.jpg
YORABTMHE200.png 10250/e26c861a_YORABTMHE200.png
SRK35ZTL-W.png 10252/d6aafded_SRK15-50ZTL-W.png
YORABTM270.png 10255/e26c861a_YORABTM270.png
RC-EH5.png 10266/5fe046ef_RC-EH5.png
FDE50VH.jpg 10268/4396ccd2_FDE50VH.jpg
SRK80ZR-WF.jpg 10269/b7b7cf7e_SRK63-100ZR-WF.jpg
SRC63ZTL-W.jpg 10272/32f9c147_SRC63-71ZTL-W.jpg
SRK15ZTL-W.png 10267/d6aafded_SRK15-50ZTL-W.png
SRK100ZR-WF.jpg 10278/b7b7cf7e_SRK63-100ZR-WF.jpg
SRK35ZS-WFT.png 10276/8904916a_SRK-ZS-WFT_titane.png
SRK45ZSP-W1.jpg 10284/e44ae349_SRK25-50ZSP-W1.jpg
SRK63ZTL-W.png 10282/08a22881_SRK63-71ZTL-W-1.png
SRK63ZR-W.jpg 10281/482b9fcc_SRK63-100ZR-W.jpg
YGSAOPH200.png 10289/473aa78f_Gaine.png
SRK50ZS-WT.png 10302/9bdf1f74_SRK20-50ZS-WT.png
UM-FL2EF.jpg 10308/2705628a_FDUM-filter.jpg
SRK35ZSX-W.jpg 10304/4519774f_SRK20-60ZSX-W.jpg
SRR60ZS-W.jpg 10306/5778b014_SRR50-60ZS-W.jpg
TC-OAS-E2.jpg 10303/d5cc831d_TC-OAS-E.jpg
Q-TON.jpg 10305/f00b814e_Q-ton%20Image.hi.res.jpg
FDT160KXZE3-W.png 10307/d9b2ff38_FDT28-160KXZE1_close.png
SRK25ZSX-W.jpg 10314/4519774f_SRK20-60ZSX-W.jpg
SRK50ZS-W.jpg 10312/fefc97ae_SRK20-50ZS-W.jpg
SRR25ZS-W.jpg 10321/fe7ba1c6_SRR25-35ZS-W.jpg
SRK35ZSX-WF.png 10324/6015d76f_SRK20-60ZSX-WF.png
TC-OAD-E.jpg 10327/d5cc831d_TC-OAD-E.jpg
RCN-E-E3.png 10332/5d6147a3_RCN-E-E3_face.png
SRK50ZTL-W.png 10331/d6aafded_SRK15-50ZTL-W.png
SRC71ZTL-W.jpg 10335/32f9c147_SRC63-71ZTL-W.jpg
SRC50ZS-W.jpg 10340/6acaeb75_SRC25-35ZS-W.jpg
SRC50ZSP-W1.jpg 10345/2bec42d6_SRC45-50ZSP-W1.jpg
MH-RC-WIFI-1B.png 10334/effd473a_MH-RC-WIFI-1B.png
SCM50ZS-W.png 10342/596a3cc1_SCM50-60ZS-W.png
UM-FL3EF.jpg 10341/2705628a_FDUM-filter.jpg
SRC63ZR-W.jpg 10351/32f9c147_SRC63ZR-W.jpg
DIS-TB1G.png 10359/f7d0fb28_DIS-TB1G-f581a196.png
SRC71ZR-W.jpg 10357/3e9ac338_SRC71-80ZR-W.jpg
FDTC35VH1.jpg 10358/ed1c9429_FDTC25-60VH_close.jpg
LB-T-5W-E.jpg 10363/6f838590_LB-T-5BW-E_MIS.jpg
SRK71ZR-W.jpg 10366/482b9fcc_SRK63-100ZR-W.jpg
RCN-TC-5AW-E3.png 10367/f93c0d54_RCN-TC-5AW-E3_face.png
SRK25ZSP-W1.jpg 10369/e44ae349_SRK25-50ZSP-W1.jpg
SRK60ZSX-WF.png 10370/6015d76f_SRK20-60ZSX-WF.png
SRK80ZR-W.jpg 10385/482b9fcc_SRK63-100ZR-W.jpg
FDC155KXZES1-W.jpg 10394/b32e58c2_FDC121-155KXZEN(S)1-W.jpg
SRK50ZS-WF.jpg 10388/d13029b5_SRK20-50ZS-WF.jpg
SRK71ZR-WF.jpg 10400/b7b7cf7e_SRK63-100ZR-WF.jpg
SRK63ZR-WF.jpg 10399/b7b7cf7e_SRK63-100ZR-WF.jpg
FDC560KXZE3.png 10397/8e4a32d5_FDC224_280_335KXZE3_profil.png
SV-KIT-S1N-E.png 10401/40ba7b33_SV-KIT-S1N-E.png
YBM50Y01.jpg 10403/345bdad6_bouteille-de-melange-bleu-chauffage-et-climatisation-pose-sur-pieds-thermador-square-650x650%20copie%202.jpg
YMOH.jpg 10528/f17290db_YMOH.jpg
RLD-KIT-E.jpg 10402/8f919cb9_RLD-KIT-E.jpg
FDTC15KXZE1-W.jpg 10421/ed1c9429_FDTC25-60VH_close.jpg
YACKBIONIC.png 10419/3fe473bd_YACKBIONIC.png
V-MULTI.png 10420/26e4f469_V-Multi_2026.png
FDUM22KXZE3-W.png 10426/d861e9eb_FDUM22-56KXE6F.png
PFD2804-E.jpg 10428/d4863a3a_PFD-01.jpg
YORARG1816.png 10447/15fcdb5a_YORARAAE64.png
FDK22KXZE1-W.png 10457/06e6819a_FDK15-56KXZE1-W.png
FDC224KXZRE2.png 10454/42bf0d03_FDC224_280_335KXRE2_profil.png
MH-RC-KNX-1I.png 10455/e169f345_MH-RC-KNX-1L.png
FDUT15KXE6F-W.jpg 10475/f7e1b7ae_FDUT15-71KXE6F-E.jpg
FDTC28KXZE3-W.jpg 10480/ed1c9429_FDTC25-60VH_close.jpg
YBOX-N04.jpg 10484/bbdd7d78_Notice%20NEOBOX04%20V240105.jpg
YRM3000T01.jpg 10488/1a993a29_Resistance%20Ballon.jpg
FDK71KXZE3-W.png 10489/06e6819a_FDK15-56KXZE1-W.png
SRK35ZSP-W1.jpg 10500/e44ae349_SRK25-50ZSP-W1.jpg
FDU280KXZE3-W.jpg 10505/49bee27d_FDU45-160KXZE3-W.jpg
T-PSA-5BW-E.png 10506/f3518525_T-PSA-5BW-E.png
FDC500KXZE2.jpg 10507/94a8ff9c_FDC280-335KXZE2.jpg
MH-AC-KMBAC-128.jpg 10521/d04b0a37_MH-AC-KMBAC-128.jpeg
FDCW-VNX-A.png 10523/a1a9dc34_HMA.png
RC-E2.png 10522/c4a895ba_mitsubishi-heavy-industries-air-conditioning-rci-mdqe2-q-ton-modbus-interface-17963-1-p.png
SV-RLY-E.png 10526/768f6e33_SV-RLY-E_face.png
HECOZ.jpg 10535/f28d1d2e_HECOZ.jpg
MH-RC-BAC-3.png 11259/570b5e37_MH-RC-BAC-3.png
SV-KIT-L1N-E.png 10556/40ba7b33_SV-KIT-L1N-E.png
DOS-3A-4.png 10683/cd9b49d7_DOS-3A-4.png
FDFU71KXZE3-W.jpg 10687/30bfee7f_FDFU28-71KXZE3-W.jpg
DIS-540-4.jpg 10690/aebbcc3a_DIS-540-3.jpg
FDTW28KXZE3-W.png 10693/52d3bc0a_FDTW_KXZE3-W.png
YPRE-FU60-2.jpg 10813/c1722c0a_YPRE-FU60-2.jpg
FDE100VH.jpg 10487/4396ccd2_FDE50VH.jpg
SC-SL2NA-E.jpg 10022/b2b5739b_SC-SL2NA-E.jpg
FDE71VH.jpg 10627/4396ccd2_FDE50VH.jpg
FDU125VH.jpg 10380/a9d92a17_FDU71-140VH.jpg
FDC125VSA-W.jpg 10365/4faffe32_FDC100-140VSA-W.jpg
FDE56KXZE3-W.png 10820/f4a903b9_FDE_profil.png
FDU250VH.png 10383/3e60a187_FDU-200-280-VH.png
FDC100VSA-W.jpg 10155/4faffe32_FDC100-140VSA-W.jpg
FDU140VH.jpg 10287/a9d92a17_FDU71-140VH.jpg
FDUM40VH.jpg 10387/a1116a0b_FDUM100-140VH.jpg
YPSR-FU60-2.png 10833/ad510aff_YPSR-FU60-S.png
FDC71VNP-W.jpg 10177/b9aed28a_FDC71VNP-W.jpg
SC-SL4.jpg 10113/d4c69de4_SC-SL4-AE3_BE3.jpg
FDUM140VH.jpg 10286/a1116a0b_FDUM100-140VH.jpg
FDT71VH.png 10579/b47498f7_FDT40-140VH_close.png
FDT140VH.png 10431/b47498f7_FDT40-140VH_close.png
FDUM125VH.jpg 10566/a1116a0b_FDUM100-140VH.jpg
100GTCMHY2.png 10843/88c156e7_100-GTCMHY.png
YPSR-FU100-2.jpg 10846/3a3b2a94_YPSR-FU100-2.jpg
FDC125VNX-W.jpg 10171/56c9e0e7_FDC100-140VNX-W.jpg
FDC140VNA-W.png 10568/237963c1_FDC100-140VNA-W.png
FDU280VH.png 10433/3e60a187_FDU-200-280-VH.png
FDE40VH.jpg 10582/4396ccd2_FDE50VH.jpg
FDUT15KXZE3-W.jpg 10859/f7e1b7ae_FDUT15-71KXE6F-E.jpg
FDE60VH.jpg 10565/4396ccd2_FDE50VH.jpg
FDC125VSX-W.jpg 10285/56c9e0e7_FDC100-140VSX-W.jpg
FDT100VH.png 10293/b47498f7_FDT40-140VH_close.png
FDT125VH.png 10598/b47498f7_FDT40-140VH_close.png
FDU200VH.png 10564/3e60a187_FDU-200-280-VH.png
YPSR-SR25-2.png 10866/9442919d_YPSR-FU25-2.png
FDC140VSA-W.jpg 10451/4faffe32_FDC100-140VSA-W.jpg
FDE140VH.jpg 10622/4396ccd2_FDE50VH.jpg
FDUM60VH.jpg 10376/a1116a0b_FDUM100-140VH.jpg
FDC140VSX-W.jpg 10423/56c9e0e7_FDC100-140VSX-W.jpg
FDE125VH.jpg 10618/4396ccd2_FDE50VH.jpg
FDC100VNP-W.jpg 10109/30e01d0b_FDC90-100VNP-W.jpg
YPRE-FU40-2.png 10878/7177fbb4_YPRE-FU40-2.png
FDC125VNA-W.png 10567/237963c1_FDC100-140VNA-W.png
YPSR-FU40-2.jpg 10879/5f6b7ff8_YPSR-FU40-2.jpg
FDU100VH.jpg 10237/a9d92a17_FDU71-140VH.jpg
FDC140VNX-W.jpg 10615/56c9e0e7_FDC100-140VNX-W.jpg
FDC100VNA-W.png 10254/237963c1_FDC100-140VNA-W.png
FDU71VH.jpg 10310/a9d92a17_FDU71-140VH.jpg
YPRE-FU100-2.jpg 10885/3a3b2a94_YPRE-FU100-2.jpg
FDC125VNP-W.jpg 10218/f7d25280_FDC125VNP-W.jpg
FDUM71VH.jpg 10298/a1116a0b_FDUM100-140VH.jpg
FDUM100VH.jpg 10246/a1116a0b_FDUM100-140VH.jpg
T-PSA-5CB-E.png 10969/9cb347a0_FDT71-140VH_T-PSA-5CB-E.png
SRC35ZT-W.png 11003/630e0770_SRC-20-35-ZT-W.png
T-PSA-5BB-E.png 11006/ae89cc72_T-PSA-5CB-E.png
FDF100VH1.png 11007/78e9d0b0_FDF-VH1.png
LOKYDYP1-18.jpg 11010/85fa6edf_LOCKYACK.jpg
LOKYDYP0-34.jpg 11014/85fa6edf_LOCKYACK.jpg
SRC20ZT-W.png 11013/630e0770_SRC-20-35-ZT-W.png
SRK50ZT-WF.png 11018/3c086270_SRK-ZT-WF_blanc.png
LOKYDYP0-38.jpg 11019/85fa6edf_LOCKYACK.jpg
SRC50ZT-WB.png 11021/a74e4315_SRC-50-ZT-W_noir.png
T-PSAE-5CB-E.png 11023/54c83ca2_T-PSAE-5CB-E_Optiflux.png
SRK50ZT-WFB.png 11025/fe9f68fe_SRK-ZT-WF_noir.png
LOKYDYP0-58.jpg 11028/85fa6edf_LOCKYACK.jpg
LOKYDYP1-38.jpg 11031/85fa6edf_LOCKYACK.jpg
SRK20ZT-WFB.png 11033/fe9f68fe_SRK-ZT-WF_noir.png
SRC25ZT-W.png 11035/630e0770_SRC-20-35-ZT-W.png
LB-T-5BW-E.jpg 11037/6f838590_LB-T-5BW-E_MIS.jpg
LOKYDYP1-58.jpg 11039/85fa6edf_LOCKYACK.jpg
LOKYDYP0-78.jpg 11045/85fa6edf_LOCKYACK.jpg
T-PSAE-5BB-E.png 11047/54c83ca2_T-PSAE-5CB-E_Optiflux.png
SRK35ZT-WFB.png 11048/fe9f68fe_SRK-ZT-WF_noir.png
T-PSAE-5CW-E.png 11052/f5de3bb0_T-PSAE-5BW-E_FDT-VH_Blanc_Optiflux.png
SRC50ZT-W.png 11051/b81a7393_SRC-50-ZT-W.png
SRK25ZT-WF.png 11055/3c086270_SRK-ZT-WF_blanc.png
SRK25ZT-WFB.png 11056/fe9f68fe_SRK-ZT-WF_noir.png
SRC35ZT-WB.png 11060/1f0a5d52_SRC-20-35-ZT-W_noir.png
SRC25ZT-WB.png 11061/1f0a5d52_SRC-20-35-ZT-W_noir.png
SRC20ZT-WB.png 11062/1f0a5d52_SRC-20-35-ZT-W_noir.png
SRK35ZT-WF.png 11066/3c086270_SRK-ZT-WF_blanc.png
SRK20ZT-WF.png 11067/3c086270_SRK-ZT-WF_blanc.png
LOKYDYP0-12.jpg 11068/85fa6edf_LOCKYACK.jpg
PANDORA.png 11102/ffeb2d6a_1_PANDORA-boitier.png
UT-BAT1EF.jpg 11138/b4d94b7c_UT_EF.jpg
UT-FL3EF.jpg 10277/b4d94b7c_UT_EF.jpg
UT-BAT2EF.jpg 11144/b4d94b7c_UT_EF.jpg
FDFW45KXZE3-W.png 10442/f4cab0d9_FDFW28KXZE3-W.png
DIS.png 11148/3972673c_DIS-180-1-RG.png
LB-KIT.png 11158/b9481de7_LB-KIT.png
FDC224KXZME1.png 11162/03137bbe_FDC224_280_335_KXZME1_profil.png
EEVKIT.png 11169/e846d9a4_EEVKIT.png
HMU140KXZE1.png 11168/073ba517_HMU_profil.png
DOS.png 11181/6293a502_DOS-2A-3.png
FDC155KXZES1.png 11180/ea804b4c_FDC112_140_155KXZES1_profil.png
FDC224KXZWE1.png 11190/f08468f4_FDC224_280_335KXZWE1_profil.png
LB-E.png 11196/5f89afc6_LB-E_face.png
FDC224KXZPE1.png 11197/bbd6d59e_FDC224-280KXZPE1_profil.png
FDUH-ACC.png 11200/a0044e17_UH-DU-E_face.png
FDC121KXZEN1.png 11206/ea804b4c_FDC112_140_155KXZEN1_profil.png
HEAD.png 11205/a05d667c_HEAD6-180-1G.png
PCZ006A043.png 11208/f02dcaf8_PCZ_B_PCZ006A043.png
SC-WBGW256.png 11210/2ab14d12_SC-WBGW256_face.png
100GTCMHY2-2.png 11258/1865dde3_GATEWAY%20YACK.png
RHOSS-EAUEAU-R290.png 11294/22933ad8_Rhoss_Eau_Eau_R1234ze_R515B.png
RHOSS-AIREAU-R32.png 11295/cb534ea0_Rhoss_Air_Eau_R1234ze_R515B.png
LISTE

echo "photos présentes : $presentes, téléchargées : $telechargees, échecs : $echecs, non tentées : $non_tentees (sur $total, en $((SECONDS - debut)) s)"
if [ -n "$arret" ]; then
    echo "photos : arrêt, $arret ; un nouveau passage reprend les photos manquantes"
fi
[ $((echecs + non_tentees)) -eq 0 ]
