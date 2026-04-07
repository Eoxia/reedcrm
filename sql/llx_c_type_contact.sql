-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

insert into llx_c_type_contact (element, source, code, libelle, active ) values ('propal',  'internal', 'SALESREPINTERNAL', 'Commercial qui a vendu la proposition', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('facture', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à la facture', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('commande', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à la commande', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('shipping', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à l''expédition', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('project', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au projet', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('societe', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au tiers', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('payment', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au paiement', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('contrat', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au contrat', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('fichinter', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à l''intervention', 1);
insert into llx_c_type_contact (element, source, code, libelle, active, module ) values ('ticket', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au ticket', 1, NULL);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('product', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au produit', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('conferenceorbooth', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à l''événement', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('project_task', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à la tâche', 1);