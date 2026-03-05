# 🔔 GLPI Plugin: Alert Telegram
**Versão:** 1.0.0
**Autor:** Wagner S. Santos

O **Alert Telegram** é um plugin de nível corporativo para GLPI (versão 10.0+) que integra o seu Helpdesk diretamente ao Telegram. Ele atua como um motor de notificações em tempo real, distribuindo alertas ricos e inteligentes para todos os atores envolvidos em um chamado, substituindo ou complementando o uso tradicional de e-mails.

---

## 🚀 Principais Funcionalidades

* **Distribuição Inteligente:** Lê a estrutura do chamado e envia notificações automáticas e individuais para Requerentes, Técnicos Atribuídos e Observadores (seja vínculo direto ou via Grupos).
* **Prevenção de Flood:** Remove IDs duplicados automaticamente. Se um usuário é Requerente e também faz parte do Grupo Técnico, ele recebe apenas uma notificação.
* **Gatilhos Abrangentes:** Detecta e notifica sobre:
  * 🎫 Abertura de novos chamados
  * 💬 Novos acompanhamentos
  * ✅ Novas tarefas
  * 💡 Soluções propostas
  * ⚠️ Pedidos de aprovação
  * 🔄 Alterações de status do chamado
* **Controle de Atores:** Notifica quando novos usuários/grupos entram no chamado (➕) e dispara um aviso de despedida para quem é removido (➖).
* **Radar de Menções (@):** Sistema de *scanner* nativo. Se você digitar `@login.do.usuario` no texto do chamado, o plugin localiza a pessoa no banco de dados e envia uma notificação puxando-a para a conversa, mesmo que ela não seja atriz direta do chamado.
* **Detector de Mídias:** Oculta lixo HTML e identifica quando imagens ou vídeos são anexados sem texto, enviando um aviso elegante de `[🖼️ Imagem/Vídeo anexado]`.
* **Auto-Reparo de BD:** Proteção nativa contra erros de integridade (Força chaves `UNSIGNED` automaticamente para manter o painel do GLPI limpo de avisos).

---

## 🛠️ Requisitos

* GLPI 10.0.0 ou superior.
* Um Token de Bot válido (gerado via `@BotFather` no Telegram).

---

## 📦 Instalação

1. Extraia a pasta `alerttelegram` dentro do diretório `plugins/` do seu servidor GLPI (ex: `/var/www/html/glpi/plugins/`).
2. Acesse o GLPI com um usuário Administrador.
3. Navegue até **Configurar > Plugins**.
4. Clique no ícone de pasta para **Instalar** o *Alert Telegram*.
5. Clique no ícone verde para **Ativar** o plugin.

---

## ⚙️ Configuração

### 1. Configuração Global (Administrador)
1. Vá em **Configurar > Plugins** e clique no ícone de **Engrenagem** ao lado do *Alert Telegram*.
2. Insira o **Token do seu Bot** gerado no Telegram.
3. Defina o **Limite de Caracteres** (Padrão: 150). Isso serve como uma "tesoura" para textos muito longos, enviando apenas um resumo para a tela do celular e mantendo a notificação objetiva.
4. Salve as configurações.

### 2. Configuração do Usuário (Chat ID)
Para que o bot saiba para onde enviar a mensagem, cada usuário precisa registrar o seu ID do Telegram no GLPI:
1. O usuário deve iniciar uma conversa com o seu Bot no Telegram (ou usar o `@userinfobot` para descobrir seu ID numérico).
2. No GLPI, o usuário clica no próprio nome (canto superior direito) para abrir o seu perfil.
3. Acessa a aba lateral **Alert Telegram**.
4. Insere o seu Chat ID numérico e clica em Salvar.

---

## 💡 Dica de Uso: Menções
Para chamar a atenção de alguém específico no meio de um acompanhamento, certifique-se de usar o **login** exato da pessoa no GLPI antecedido por um `@`. 
*Exemplo: "Por favor, @wagner analise essa situação."* O robô interceptará a mensagem e notificará o Wagner instantaneamente no Telegram.
