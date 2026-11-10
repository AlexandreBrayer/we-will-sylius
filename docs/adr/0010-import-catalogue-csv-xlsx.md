# ADR 0010 : import manuel du catalogue en CSV et XLSX (mode dégradé de la synchronisation PIM/ERP)

- Statut : proposé (à valider par Thomas). Nouvelle dépendance Composer : OpenSpout (paquet `openspout`, ^4.28, licence MIT).
- Contexte : le CDC §3.3.3 demande un tableau de bord des synchronisations PIM/ERP et un import de secours quand l'API Yack ne répond pas. Les équipes de Yack exportent leurs tarifs depuis Excel ou leur ERP : fichiers `.xlsx`, ou CSV dont l'encodage (UTF-8, Windows-1252) et le séparateur (point-virgule en France, virgule, tabulation) varient selon l'outil et le poste.

## Décision

1. **Deux formats, CSV et XLSX**, détectés sur le contenu (`fileinfo`), jamais sur l'extension ni le type déclaré par le navigateur. Le fichier est rangé hors du dossier public (`var/yack/private/catalog-import/`, nom aléatoire, ADR 0005), supprimé après l'import, l'annulation, ou au bout d'un jour. Limites : 5 Mo, 5 000 lignes.
2. **Contrat de colonnes unique** (`src/Yack/Catalog/Domain/Import/ImportColumn.php`) : chaque colonne correspond à un champ du flux de l'API Yack. Une ligne devient un enregistrement du flux et suit le même mapping (`ProductRecordMapper`) et la même écriture (`ApplyProductFeed`) qu'une synchronisation : pas de seconde règle métier. En-têtes reconnus en français ou en anglais, dans n'importe quel ordre ; nombres avec virgule ou point décimal ; colonne inconnue ignorée.
3. **CSV lu sans bibliothèque** (`fgetcsv`) : BOM UTF-8 retiré, contenu non UTF-8 converti depuis Windows-1252, séparateur choisi sur la ligne d'en-tête (le plus fréquent parmi `;`, `,`, tabulation).
4. **XLSX lu et écrit avec OpenSpout** (`src/Yack/Catalog/Infrastructure/Import/SpreadsheetFileReader.php`, `ImportTemplates`), première feuille seulement.
5. **Aperçu obligatoire** avant l'écriture : colonnes reconnues, lignes rejetées avec leur raison, écart champ par champ avec la boutique (même calcul qu'une simulation). L'import confirmé est une synchronisation du journal (source « Import CSV » ou « Import XLSX ») ; ses rejets se corrigent et se rejouent comme ceux de l'API.

## Pourquoi OpenSpout

| Option | Écartée ou retenue |
|---|---|
| PhpSpreadsheet | Écartée : charge tout le classeur en mémoire (plusieurs centaines de Mo pour quelques milliers de lignes), calcul de formules et mise en forme inutiles ici |
| CSV seul | Écartée : oblige l'administrateur à réenregistrer son classeur, source d'erreurs d'encodage et de séparateur |
| OpenSpout | Retenue : lecture et écriture en flux (mémoire constante), PHP pur (extensions `zip` et `xmlreader` déjà présentes), MIT, maintenue, successeur de Box/Spout |

L'adapter est le seul code qui l'importe : la remplacer revient à réécrire `SpreadsheetFileReader` et `ImportTemplates`, le `Domain` (`SpreadsheetReader`, `SpreadsheetRows`) ne change pas.

## Conséquences

- Les formats `.xls` (Excel 97) et `.ods` ne sont pas acceptés : message « Format non accepté ». OpenSpout lit l'ODS ; l'ajouter est un cas de plus dans l'adapter si Yack en a besoin.
- Les formules XLSX sont lues par leur dernière valeur calculée et enregistrée par Excel.
- Une ligne décrit le produit complet, comme le flux de l'API : une description absente du fichier est vidée, une caractéristique absente reste telle quelle. L'aperçu montre ces écarts avant l'écriture.
