# Rattacher les anomalies aux sous-tâches

Date : 2026-10-13. Statut : validé le 2026-10-13.

## 1. Objectif

Un gestionnaire rattache une anomalie aux sous-tâches qui la réparent, et plus aux tâches entières.

Cas d'origine : l'anomalie « pneu de roulette de queue usé » se répare pendant la visite annuelle, qui compte des dizaines de sous-tâches. Avec le lien actuel, le gestionnaire rattache toute la visite : l'anomalie reste « prise en charge » jusqu'à la clôture de la visite, et personne ne voit quelle étape la répare.

Hors périmètre :
- rattacher une anomalie depuis la fiche d'une tâche ou d'une sous-tâche (le lien se crée depuis l'anomalie, comme aujourd'hui) ;
- rattachement en lot depuis la liste des anomalies ;
- passage automatique à « traitée » : le gestionnaire confirme toujours à la main ;
- toute notification nouvelle.

À éviter : une anomalie liée à 34 sous-tâches d'une visite par effet de bord (migration, création de tâche). Ce serait le problème de départ sous une autre forme.

## 2. Modèle de données

- `Anomaly::$tasks` (ManyToMany, table `anomaly_task`) disparaît. Il est remplacé par `Anomaly::$subTasks`, ManyToMany vers `SubTask`, table `anomaly_sub_task`, `ON DELETE CASCADE` des deux côtés. Côté `SubTask`, la relation inverse `$anomalies`.
- `Task::$anomalies` (relation inverse) disparaît. La tâche expose une méthode dérivée qui rassemble, sans doublon, les anomalies de ses sous-tâches. Le bloc « Anomalies » de la fiche tâche s'en sert.
- Une sous-tâche liée appartient à une tâche du même matériel que l'anomalie. Le contrôleur le vérifie à chaque rattachement, comme il vérifie aujourd'hui l'égalité des matériels.

## 3. Statut de l'anomalie

Même logique qu'aujourd'hui, appliquée aux sous-tâches. Le statut reste dérivé, jamais stocké.

- Sous-tâches actives : les sous-tâches liées dont le statut n'est pas `cancelled` **et dont la tâche n'est pas `cancelled`**. La double condition compte : `TaskStatusService::handleCancelTask` n'annule que les sous-tâches `open` et laisse `done` et `closed` en l'état. Sans elle, une tâche annulée avec une sous-tâche close rendrait l'anomalie traitable, alors qu'aujourd'hui elle repasse en « Relevé ». (Choix de rédaction : reproduire le comportement actuel.)
- « Prise en charge » : au moins une sous-tâche active.
- « Marquer comme traitée » possible quand l'anomalie n'est pas résolue, a au moins une sous-tâche active, et que toutes ses sous-tâches actives ont le statut `closed`. Une sous-tâche `done` en attente d'inspection ne suffit pas.
- « Traitée » et « Ignorée » priment, comme aujourd'hui.

Le filtre par statut de la liste des anomalies (`AnomalyRepository`, sous-requête `anomaly_with_task`) passe sur les sous-tâches avec la même règle, jointure sur la tâche parente comprise.

## 4. Parcours

### 4.1 Page de l'anomalie

Le bloc « Tâches liées » devient « Sous-tâches liées ». Chaque ligne affiche le titre de la sous-tâche, le titre de sa tâche en dessous, le badge de statut de la sous-tâche, un bouton vers la sous-tâche et, pour qui a le droit, un bouton « Délier ».

Les deux actions actuelles (« Créer une tâche » et le menu « Lier une tâche existante ») laissent place à un bouton unique, « Lier des sous-tâches », visible pour les gestionnaires tant que l'anomalie n'est pas résolue (règle actuelle de `AnomalyVoter::LINK_TASK`). Le bouton apparaît dans le bloc et dans l'en-tête de page, à l'emplacement de l'actuel « Créer une tâche ».

### 4.2 Écran 1 : « Dans quelle tâche ? »

Page dédiée, accessible depuis le bouton. Elle liste, en cartes cliquables, les tâches non annulées du matériel de l'anomalie, en deux groupes :

- les tâches en cours (`open` et `done`), dépliées, les plus récentes d'abord ;
- les tâches closes, repliées derrière « Afficher les tâches closes (N) », les plus récemment closes d'abord. Elles couvrent l'anomalie signalée après la réparation, possible aujourd'hui avec le lien aux tâches (décision validée : accessibles, repliées).

Chaque carte affiche le titre de la tâche, « N / M sous-tâches closes » (M compte les sous-tâches non annulées) et une barre d'avancement. Une carte en pointillés, après les tâches en cours, propose « Nouvelle tâche dédiée ».

Sans tâche en cours sur le matériel, la page affiche « Nouvelle tâche dédiée » et, s'il y en a, le groupe replié des tâches closes.

### 4.3 Écran 2 : « Quelles sous-tâches réparent l'anomalie ? »

Après le choix d'une tâche. En tête, un lien en pointillés « Créer une sous-tâche dans cette tâche », affiché seulement si `TaskVoter::CREATE_SUBTASK` l'autorise. Ce voter refuse les tâches `done`, `closed` et `cancelled` : dans une tâche close, le gestionnaire ne peut que cocher. En dessous, les sous-tâches non annulées de la tâche, en cases à cocher, rangées en deux groupes : « À faire » (`open` et `done`) puis « Déjà closes » (`closed`). Chaque ligne porte son badge de statut. Les sous-tâches déjà liées à l'anomalie apparaissent cochées et inactives.

Un champ de filtre au-dessus de la liste masque les lignes dont le titre ne contient pas le texte saisi, côté navigateur. Une visite compte des dizaines de sous-tâches.

Le bouton de validation affiche le nombre coché (« Lier 2 sous-tâches ») et reste inactif à zéro. Soumission en POST avec jeton CSRF, puis retour sur l'anomalie avec un message de confirmation.

Pour lier des sous-tâches d'une autre tâche, le gestionnaire refait le parcours.

### 4.4 Créer une sous-tâche dans la tâche choisie

Le lien ouvre le formulaire de sous-tâche existant (`club_subtask_new`) avec l'anomalie en paramètre. Titre et description pré-remplis depuis l'anomalie. Le fil d'Ariane et l'en-tête rappellent l'anomalie, comme le fait aujourd'hui `club/task/new.html.twig`. À l'enregistrement, la sous-tâche est créée, liée à l'anomalie, et le gestionnaire revient sur l'anomalie. Droits : ceux de l'anomalie (lier) et `TaskVoter::CREATE_SUBTASK` sur la tâche.

### 4.5 Nouvelle tâche dédiée

La carte ouvre le formulaire de tâche existant (`club_task_new?anomaly=…`), déjà prévu pour ce cas : matériel imposé, titre pré-rempli. À l'enregistrement, toutes les sous-tâches saisies dans le formulaire se lient à l'anomalie : une tâche dédiée sert à cette réparation. La règle « pilote requis pour un aéronef » reste en place.

## 5. Affichages ailleurs

- Fiche sous-tâche : un bloc « Anomalies » liste les anomalies que la sous-tâche répare, sur le modèle du bloc de la fiche tâche (titre, badge de statut, badge d'impact si l'anomalie immobilise le matériel, bouton vers l'anomalie).
- Fiche tâche : le bloc « Anomalies » existant reste, alimenté par les sous-tâches (§2).
- Carte d'anomalie (`component/anomalyCard.html.twig`) : le compteur « N tâches » devient « N sous-tâches ».

## 6. Journal d'activité

Deux nouveaux types, `SUBTASK_LINKED` et `SUBTASK_UNLINKED`. Les entrées `TASK_LINKED` / `TASK_UNLINKED` existantes gardent leur libellé : leur message contient un titre de tâche, et un libellé « Sous-tâche liée » mentirait sur l'historique.

À chaque rattachement ou détachement, une entrée côté anomalie (message : titre de la sous-tâche) et une entrée miroir côté sous-tâche (message : titre de l'anomalie), comme `AnomalyStatusService::handleLinkTask` le fait aujourd'hui avec la tâche. Un lien multiple produit une paire d'entrées par sous-tâche.

## 7. Migration des liens existants

Une migration Doctrine (PostgreSQL) :
1. crée `anomaly_sub_task` ;
2. copie chaque lien `anomaly_task` vers toutes les sous-tâches non annulées de la tâche (`INSERT … SELECT … JOIN sub_task WHERE status <> 'cancelled'`, sans doublon). Un lien vers une tâche annulée n'est pas copié : il ne compte déjà plus aujourd'hui. En prod, un seul cas (anomalie 35, déjà traitée, sous-tâche annulée) ;
3. supprime `anomaly_task`.

Pas d'entrée de journal pour ces liens recopiés. Le `down()` recrée `anomaly_task` à partir des tâches parentes des sous-tâches liées.

État de la prod le 2026-10-13 (lecture seule) : 18 liens, 12 anomalies, 4 clubs.
- broceliande : 7 anomalies, dont 3 non résolues, toutes sur des tâches dédiées de 1 à 3 sous-tâches.
- cvve : « 2 enfoncements de la queue… » (tâche dédiée, 6 sous-tâches) et « Bruit métallique train principal » (tâche large et close, 9 sous-tâches, toutes reprises par la conversion ; un gestionnaire déliera le superflu).
- demo : 2 anomalies sur des tâches larges (22 + 10 et 43 sous-tâches). Club de démonstration, sans enjeu.
- asvv : 1 anomalie de test.

Décision validée : conversion automatique, pas de suppression. Supprimer aurait fait repasser 5 anomalies réelles en « Relevé » à l'insu de leurs gestionnaires.

## 8. Guide utilisateur

Section 9 de `templates/public/guide/index.html.twig` (« Signaler une anomalie ») : le statut se déduit des sous-tâches, le bouton « Lier des sous-tâches » et ses deux écrans, la nouvelle tâche dédiée, l'affichage sur la fiche sous-tâche. Même commit que le code.

## 9. Tests

- `tests/Entity/AnomalyTest.php` : les cas actuels réécrits avec des sous-tâches, plus une sous-tâche `done` en attente d'inspection qui bloque « traitée ».
- `tests/Service/Maintenance/AnomalyStatusServiceTest.php` : lien et détachement de sous-tâches, idempotence, journal des deux côtés.
- Un test fonctionnel du rattachement multiple qui rejette une sous-tâche d'un autre matériel.

## 10. Laissé au développeur

- Noms des routes, du formulaire de l'écran 2 et du contrôleur Stimulus de filtre (réutiliser un contrôleur existant s'il convient).
- Renommer ou non `AnomalyVoter::LINK_TASK` (la règle ne change pas).
- Forme exacte de la méthode dérivée `Task` → anomalies.
- Clés de traduction (XLF, `lowerCamelCase`, ajout manuel) et suppression des clés devenues mortes (`anomalyCreateTask`, `anomalyLinkExistingTask`…) après vérification à la main, `debug:translation` n'étant pas fiable sur l'état `unused`.
