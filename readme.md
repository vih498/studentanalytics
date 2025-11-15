# Moodle Student Analytics (EduMetrics)

## Descrição
Plugin para Moodle que coleta, processa e apresenta métricas de engajamento, frequência, participação em fóruns e entregas de atividades dos estudantes. Permite exportação de dados e pode gerar alertas automáticos de risco de evasão.

## Funcionalidades
- Ranking dos 10 alunos mais ativos.
- Tempo médio de acesso por aluno.
- Participação em fóruns.
- Entregas de atividades.
- Dashboard interativo com gráficos (Chart.js).
- Placeholder para análise de correlação engajamento x notas.

## Instalação
1. Copie a pasta `studentanalytics` para `/local/` do Moodle.
2. Acesse `/admin` no Moodle e instale o plugin.
3. Configure os usuários e permissões conforme necessário.

## Requisitos
- Moodle 4.1+.
- PHP 7.4+.
- Base de dados MySQL/MariaDB/Postgres.
- Extensão Intl, MBString, CURL, GD, XML, etc. habilitadas.

## Próximos passos
- Implementar exportação de dados para CSV/Excel.
- Adicionar integração com Python e Scikit-learn para prever risco de evasão.