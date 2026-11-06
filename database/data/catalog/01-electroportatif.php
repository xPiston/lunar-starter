<?php

declare(strict_types=1);

/**
 * Outillage électroportatif — le rayon qui fait entrer les gens.
 *
 * `photo` est la recherche envoyée à Wikimedia Commons, en anglais parce que
 * c'est la langue de son index : « perceuse » y trouve trois fichiers, « drill »
 * quelques milliers. Un mot préfixé de `-` disqualifie un titre : c'est ce qui
 * écarte le cocktail qui s'appelle screwdriver et le papillon qui s'appelle
 * Paint-brush Swift.
 *
 * `variants` : [valeur de l'option, prix en centimes, stock]. Les stocks sont
 * volontairement variés pour montrer les trois états de la vitrine — disponible,
 * « plus que N », et rupture avec le bouton désactivé.
 */
return [
    'name' => 'Outillage électroportatif',
    'products' => [
        [
            'name' => 'Perceuse-visseuse sans fil 18 V',
            'photo' => 'cordless drill',
            'pitch' => 'Deux vitesses mécaniques et un couple de 50 Nm : elle perce le bois de part en part et visse une terrasse entière sans forcer. Le mandrin auto-serrant se change à une main, ce qui compte quand on est sur une échelle.',
            'specs' => ['Couple 50 Nm, deux vitesses', 'Mandrin auto-serrant 13 mm', 'Éclairage LED et crochet de ceinture'],
            'option' => 'Batterie',
            'variants' => [['2 Ah', 12900, 18], ['4 Ah', 15900, 7], ['5 Ah', 18900, 0]],
        ],
        [
            'name' => 'Perforateur burineur SDS-Plus 800 W',
            'photo' => 'rotary hammer drill',
            'pitch' => 'Pour le béton, là où une perceuse à percussion abandonne. Trois modes — perçage, percussion, burinage — et un embrayage de sécurité qui lâche avant votre poignet si le foret se bloque.',
            'specs' => ['Énergie de frappe 2,8 J', 'Mandrin SDS-Plus', 'Livré en coffret avec trois forets'],
            'option' => null,
            'variants' => [[null, 14900, 11]],
        ],
        [
            'name' => 'Visseuse à chocs 18 V',
            'photo' => 'impact driver tool -underwater',
            'pitch' => 'Elle visse ce que la perceuse refuse : tirefonds, vis de terrasse, boulons rouillés. Le choc remplace la force du poignet, donc rien ne ripe et rien ne se tord.',
            'specs' => ['Couple 180 Nm', 'Porte-embout 1/4" à changement rapide', 'Trois vitesses présélectionnées'],
            'option' => 'Batterie',
            'variants' => [['2 Ah', 11900, 9], ['4 Ah', 14500, 5]],
        ],
        [
            'name' => 'Meuleuse d\'angle 125 mm 900 W',
            'photo' => 'angle grinder',
            'pitch' => 'Tronçonner un fer à béton, ébavurer une soudure, décaper un portail. Le carter se règle sans outil et le redémarrage est bridé, ce qui évite le coup de poignet après une coupure de courant.',
            'specs' => ['Disque 125 mm, 11 000 tr/min', 'Poignée latérale sur trois positions', 'Blocage d\'arbre pour changer de disque'],
            'option' => null,
            'variants' => [[null, 7900, 14]],
        ],
        [
            'name' => 'Scie circulaire 190 mm 1400 W',
            'photo' => 'circular saw',
            'pitch' => 'La coupe droite et répétable dans les panneaux : profondeur 65 mm, semelle inclinable à 45°, et un guide parallèle qui tient vraiment le trait sur toute la longueur.',
            'specs' => ['Profondeur de coupe 65 mm à 90°', 'Semelle inclinable 0 à 45°', 'Raccord d\'aspiration 35 mm'],
            'option' => null,
            'variants' => [[null, 10900, 8]],
        ],
        [
            'name' => 'Scie sauteuse pendulaire 750 W',
            'photo' => 'jigsaw power tool',
            'pitch' => 'Celle qui fait les découpes que les autres ne savent pas faire : une arrondie dans un plan de travail, une ouverture au milieu d\'un panneau. Mouvement pendulaire sur quatre positions pour choisir entre vitesse et propreté du trait.',
            'specs' => ['Coupe 80 mm dans le bois, 10 mm dans l\'acier', 'Changement de lame sans outil', 'Soufflerie qui dégage le trait de coupe'],
            'option' => null,
            'variants' => [[null, 8900, 12]],
        ],
        [
            'name' => 'Ponceuse excentrique 125 mm',
            'photo' => 'orbital sander',
            'pitch' => 'Le ponçage sans trace circulaire, du décapage à la finition. L\'aspiration intégrée prend l\'essentiel de la poussière, le reste part dans le sac — et un volet de table de salle à manger se fait en une après-midi.',
            'specs' => ['Plateau auto-agrippant 125 mm', 'Vitesse variable 7 000 à 12 000 tr/min', 'Bac à poussière et raccord d\'aspirateur'],
            'option' => null,
            'variants' => [[null, 6900, 16]],
        ],
        [
            'name' => 'Ponceuse à bande 900 W',
            'photo' => 'belt sander',
            'pitch' => 'Quand il y a beaucoup de matière à enlever et peu de temps : une porte à recaler, un parquet à reprendre dans un angle. Brutale, donc à tenir à deux mains et à ne jamais laisser au même endroit.',
            'specs' => ['Bande 75 × 533 mm', 'Deux poignées, marche continue verrouillable', 'Cadre de ponçage pour rester à plat'],
            'option' => null,
            'variants' => [[null, 11900, 4]],
        ],
        [
            'name' => 'Défonceuse 1200 W avec coffret de fraises',
            'photo' => 'wood router -cnc',
            'pitch' => 'Elle transforme une planche en pièce de menuiserie : feuillures, rainures, chants arrondis, mortaises. Le réglage de profondeur au millimètre est ce qui sépare un assemblage propre d\'un à peu près.',
            'specs' => ['Pinces 6 et 8 mm', 'Course verticale 55 mm avec butée revolver', 'Six fraises en coffret'],
            'option' => null,
            'variants' => [[null, 13900, 6]],
        ],
        [
            'name' => 'Rabot électrique 82 mm',
            'photo' => 'electric planer -english -lightening -aircraft',
            'pitch' => 'Une porte qui frotte en haut, un bastaing trop épais de trois millimètres. Deux fers réversibles et une prise de passe réglable au quart de millimètre.',
            'specs' => ['Largeur de rabotage 82 mm', 'Prise de passe 0 à 3 mm', 'Deux fers carbure réversibles'],
            'option' => null,
            'variants' => [[null, 8500, 7]],
        ],
        [
            'name' => 'Cloueur-agrafeuse électrique',
            'photo' => 'staple gun -fur',
            'pitch' => 'Pour tout ce qui se fixe par centaines : lambris, tasseaux de contre-cloison, toile sur châssis, grillage. Deux coups par seconde, et une main reste libre pour tenir la pièce.',
            'specs' => ['Clous 15 à 32 mm, agrafes 6 à 14 mm', 'Sécurité de nez obligatoire', 'Chargeur de 100 attaches'],
            'option' => null,
            'variants' => [[null, 6500, 13]],
        ],
        [
            'name' => 'Aspirateur de chantier 30 L eau et poussière',
            'photo' => 'wet dry vacuum',
            'pitch' => 'Celui qui avale les gravats, l\'eau d\'une fuite et la poussière de plâtre sans rendre l\'âme. La prise asservie démarre l\'aspirateur quand l\'outil branché dessus démarre, ce qui change la vie en ponçage.',
            'specs' => ['Cuve inox 30 L, 1400 W', 'Prise asservie pour outil électroportatif', 'Décolmatage du filtre d\'un quart de tour'],
            'option' => null,
            'variants' => [[null, 15900, 5]],
        ],
        [
            'name' => 'Outil multifonction oscillant 300 W',
            'photo' => 'oscillating tool -knife',
            'pitch' => 'L\'outil des situations impossibles : scier une plinthe en place, araser un joint, découper une trappe au milieu d\'un plancher. On change de lame, on change de métier.',
            'specs' => ['Oscillation 3,2°, 20 000 cycles/min', 'Changement de lame sans outil', 'Cinq accessoires en coffret'],
            'option' => null,
            'variants' => [[null, 7500, 10]],
        ],
        [
            'name' => 'Pistolet à air chaud 2000 W',
            'photo' => 'heat gun tool',
            'pitch' => 'Décaper une peinture ancienne, dégripper un écrou, poser une gaine thermorétractable, décoller un lino. Deux débits et un thermostat jusqu\'à 600 °C.',
            'specs' => ['Deux débits, 350 et 600 °C', 'Quatre buses interchangeables', 'Protection anti-surchauffe'],
            'option' => null,
            'variants' => [[null, 3900, 21]],
        ],
        [
            'name' => 'Perceuse à percussion filaire 850 W',
            'photo' => 'impact drill',
            'pitch' => 'La filaire qu\'on garde à côté de la sans-fil : couple constant, pas de batterie à plat au milieu d\'un mur porteur. Percussion débrayable pour visser aussi.',
            'specs' => ['850 W, 0 à 3000 tr/min', 'Mandrin automatique 13 mm', 'Percussion débrayable'],
            'option' => null,
            'variants' => [[null, 6900, 15]],
        ],
        [
            'name' => 'Scie à onglet radiale 210 mm',
            'photo' => 'miter saw -squadron',
            'pitch' => 'Coupe d\'onglet répétable au degré près : c\'est elle qui fait des plinthes qui se rejoignent. La fonction radiale double la largeur de coupe, jusqu\'à 300 mm.',
            'specs' => ['Lame 210 mm, coupe 300 × 60 mm', 'Onglet 45° des deux côtés', 'Laser de trait et sac à poussière'],
            'option' => null,
            'variants' => [[null, 18900, 5]],
        ],
        [
            'name' => 'Scie sur table 1500 W',
            'photo' => 'table saw -burnt',
            'pitch' => 'Pour refendre des panneaux en série, là où une scie circulaire à main dérive. Guide parallèle sur rail et carter à couteau diviseur, qui est la pièce qui évite le rejet.',
            'specs' => ['Lame 250 mm, hauteur 85 mm', 'Guide parallèle et guide d\'onglet', 'Couteau diviseur et protecteur'],
            'option' => null,
            'variants' => [[null, 24900, 3]],
        ],
        [
            'name' => 'Mélangeur à peinture et mortier 1400 W',
            'photo' => 'mortar mixer',
            'pitch' => 'Gâcher un sac de mortier à la truelle prend vingt minutes et ne sera jamais homogène. Deux vitesses, tige hélicoïdale de 120 mm, et c\'est fait en deux.',
            'specs' => ['1400 W, deux vitesses', 'Tige mélangeuse Ø 120 mm incluse', 'Emmanchement M14'],
            'option' => null,
            'variants' => [[null, 8900, 8]],
        ],
        [
            'name' => 'Pistolet à peinture électrique HVLP 600 W',
            'photo' => 'paint sprayer -pneumatic',
            'pitch' => 'Pour un portail, des volets ou une palissade : au pistolet, c\'est une demi-journée au lieu de deux, et le rendu est tendu. Trois jets et un débit réglable.',
            'specs' => ['600 W, débit 800 ml/min', 'Godet 800 ml, 3 jets', 'Buses 1,8 et 2,6 mm'],
            'option' => null,
            'variants' => [[null, 7900, 9]],
        ],
        [
            'name' => 'Décapeur thermique à affichage digital',
            'photo' => 'heat gun -howitzer -strap',
            'pitch' => 'Température au degré près de 50 à 650 : on dégrippe à basse température sans brûler le joint d\'à côté, ce qu\'un modèle à deux positions ne permet pas.',
            'specs' => ['2000 W, 50 à 650 °C au degré', 'Affichage digital, mémoire', 'Quatre buses incluses'],
            'option' => null,
            'variants' => [[null, 5900, 11]],
        ],
        [
            'name' => 'Poste à souder inverter 160 A',
            'photo' => 'welding machine inverter',
            'pitch' => 'Inverter : quatre kilos au lieu de trente, et un arc stable même sur une rallonge. Électrodes de 1,6 à 3,2, de quoi souder un portail ou une remorque.',
            'specs' => ['160 A, électrodes 1,6 à 3,2 mm', 'Hot start et anti-collage', 'Livré avec porte-électrode et masse'],
            'option' => null,
            'variants' => [[null, 13900, 6]],
        ],
        [
            'name' => 'Compresseur d\'air 24 L 1,5 ch',
            'photo' => 'air compressor',
            'pitch' => 'Vingt-quatre litres : assez pour gonfler, souffler, agrafer et peindre au pistolet par intermittence. Au-delà, il faut une cuve de cinquante.',
            'specs' => ['Cuve 24 L, 8 bars, 1,5 ch', 'Débit 180 L/min', 'Manomètres et détendeur'],
            'option' => null,
            'variants' => [[null, 14900, 7]],
        ],
    ],
];
