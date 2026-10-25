#!/usr/bin/env python3
"""Gera as migrations Laravel a partir de database/schema.sql.

schema.sql é a única fonte de verdade do esquema. Cada módulo abaixo corta o
ficheiro entre dois marcadores e produz uma migration que executa esse SQL.
Uso: python3 tools/build_migrations.py
"""
import re
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
SCHEMA = (ROOT / "database" / "schema.sql").read_text(encoding="utf-8")
OUT = ROOT.parent / "backend" / "database" / "migrations"

# (nome, marcador onde o módulo seguinte começa)
MODULES = [
    ("platform_identity", "Catálogos globais"),
    ("geography_grade_levels", "Perfil escolar da instituição"),
    ("school_profile", "Alunos, encarregados e filiação"),
    ("students_guardians", "Estrutura escolar: anos"),
    ("school_structure", "Matrículas escolares"),
    ("enrollments", "Avaliação (classes 1 a 9"),
    ("assessment", "Documentos oficiais: numeração"),
    ("official_documents", "CREATE TABLE courses ("),
    ("training", "CREATE TABLE attendance_records ("),
    ("attendance", "CREATE TABLE charges ("),
    ("finance", "CREATE TABLE certificates ("),
    ("certificates_documents_audit", None),
]


def cut_index(text: str, marker: str, start: int) -> int:
    i = text.index(marker, start)
    if marker.startswith("CREATE"):
        return i
    # recua até à linha "-- =====" que abre o cabeçalho
    return text.rindex("-- =====", start, i)


body = SCHEMA.split("BEGIN;", 1)[1].rsplit("COMMIT;", 1)[0]
pos, slices = 0, []
for name, marker in MODULES:
    end = len(body) if marker is None else cut_index(body, marker, pos)
    slices.append((name, body[pos:end].strip()))
    pos = end

for old in OUT.glob("2026_10_25_*_create_*_tables.php"):
    old.unlink()
OUT.mkdir(parents=True, exist_ok=True)

for n, (name, sql) in enumerate(slices, start=1):
    tables = re.findall(r"CREATE TABLE (\w+)", sql)
    drops = "\n".join(f"        DB::unprepared('DROP TABLE IF EXISTS {t} CASCADE');" for t in reversed(tables))
    php = f"""<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

/**
 * GERADO por tools/build_migrations.py a partir de database/schema.sql.
 * Não editar à mão: altere o schema.sql e volte a gerar.
 */
return new class extends Migration
{{
    public function up(): void
    {{
        DB::unprepared(<<<'SQL'
{sql}
SQL);
    }}

    public function down(): void
    {{
{drops}
    }}
}};
"""
    (OUT / f"2026_10_25_{n:06d}_create_{name}_tables.php").write_text(php, encoding="utf-8")
    print(f"{n:02d} {name}: {len(tables)} tabelas")
