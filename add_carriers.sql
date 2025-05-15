-- Fichier SQL pour ajouter de nouveaux transporteurs
-- Exécuter ce fichier dans phpMyAdmin ou un autre outil de gestion MySQL

-- Transporteur 1 : Livraison Standard
INSERT INTO carrier (name, description, price) 
VALUES ('Colissimo', 'Livraison standard en 3 à 5 jours ouvrables. Service fiable et économique pour vos colis.', 495);

-- Transporteur 2 : Livraison Express
INSERT INTO carrier (name, description, price) 
VALUES ('Chronopost', 'Livraison express en 24h garantie. Idéal pour les envois urgents avec suivi en temps réel.', 995);

-- Transporteur 3 : Livraison Internationale
INSERT INTO carrier (name, description, price) 
VALUES ('DHL International', 'Service de livraison internationale avec suivi douanier et livraison en 3 à 7 jours ouvrables.', 1495);

-- Transporteur 4 : Livraison Économique
INSERT INTO carrier (name, description, price) 
VALUES ('Mondial Relay', 'Livraison en point relais près de chez vous. Économique et pratique pour récupérer vos colis.', 295);

-- Transporteur 5 : Livraison Premium
INSERT INTO carrier (name, description, price) 
VALUES ('UPS Premium', 'Service premium avec livraison sur rendez-vous et assurance incluse pour vos colis de valeur.', 1295);
