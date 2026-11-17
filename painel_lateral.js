(function () {
  const style = document.createElement("style");
  style.textContent = `
    .btn-acao-topo {
      width: auto !important;
      margin-top: 0 !important;
      padding: 6px 12px !important;
      font-size: 14px !important;
      border-radius: 20px !important;
      background-color: #7607aa;
      color: white;
      border: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      cursor: pointer;
      font-weight: bold;
    }
    .btn-acao-topo:hover {
      background-color: #6a1a93;
    }
    .modal-direita-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
      z-index: 3000;
      justify-content: flex-end;
    }
    .modal-direita-overlay.aberto {
      display: flex;
    }
    .modal-direita-painel {
      background: #fff;
      width: 380px;
      max-width: 90vw;
      height: 100%;
      padding: 20px;
      box-shadow: -4px 0 15px rgba(0, 0, 0, 0.25);
      display: flex;
      flex-direction: column;
      text-align: left;
      color: #333;
      overflow-y: auto;
    }
    .modal-direita-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #935394;
      padding-bottom: 10px;
      margin-bottom: 15px;
    }
    .modal-direita-header h4 {
      margin: 0;
      color: #543583;
      font-size: 18px;
      font-weight: bold;
    }
    .btn-fechar-lateral {
      width: auto !important;
      margin: 0 !important;
      padding: 4px 10px !important;
      font-size: 14px !important;
      background: #935394 !important;
      color: white !important;
      border: none !important;
      border-radius: 50% !important;
      cursor: pointer;
    }
    .card-info-lateral {
      background: #f8f5ee;
      border-left: 4px solid #7607aa;
      padding: 10px 12px;
      border-radius: 8px;
      margin-bottom: 10px;
      font-size: 14px;
    }
    .badge-perfil {
      display: inline-block;
      background: #7607aa;
      color: #fff;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 12px;
      text-transform: uppercase;
      font-weight: bold;
    }
    .filtro-prof-box {
      background: #f0ece2;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .filtro-prof-box input, .filtro-prof-box select {
      width: 100% !important;
      padding: 6px 10px !important;
      border-radius: 8px !important;
      border: 1px solid #935394 !important;
      font-size: 13px !important;
    }
    .btn-sair-conta {
      width: 100% !important;
      margin-top: auto !important;
      background: #c62828 !important;
      color: white !important;
      padding: 8px !important;
      border-radius: 20px !important;
      border: none !important;
      font-weight: bold;
      cursor: pointer;
    }
  `;
  document.head.appendChild(style);

  const modaisHTML = `
    <!-- Modal Lateral Notificações -->
    <div id="modalNotificacoes" class="modal-direita-overlay" onclick="if(event.target === this) fecharPainelDireita('modalNotificacoes')">
      <div class="modal-direita-painel">
        <div class="modal-direita-header">
          <h4>Notificações</h4>
          <button type="button" class="btn-fechar-lateral" onclick="fecharPainelDireita('modalNotificacoes')">✕</button>
        </div>
        <div id="listaNotificacoes">
          <p style="color: #666; text-align: center; margin-top: 20px;">sem notificações</p>
        </div>
      </div>
    </div>

    <!-- Modal Lateral Materiais -->
    <div id="modalMateriais" class="modal-direita-overlay" onclick="if(event.target === this) fecharPainelDireita('modalMateriais')">
      <div class="modal-direita-painel">
        <div class="modal-direita-header">
          <h4>Materiais Didáticos</h4>
          <button type="button" class="btn-fechar-lateral" onclick="fecharPainelDireita('modalMateriais')">✕</button>
        </div>
        <div id="listaMateriais">
          <p style="color: #666; text-align: center; margin-top: 20px;">Nenhum material disponível.</p>
        </div>
      </div>
    </div>

    <!-- Modal Lateral Perfil do Usuário -->
    <div id="modalPerfil" class="modal-direita-overlay" onclick="if(event.target === this) fecharPainelDireita('modalPerfil')">
      <div class="modal-direita-painel">
        <div class="modal-direita-header">
          <h4>Meu Perfil</h4>
          <button type="button" class="btn-fechar-lateral" onclick="fecharPainelDireita('modalPerfil')">✕</button>
        </div>
        <div id="conteudoPerfil" style="flex: 1;">
          <p style="color: #666; text-align: center; margin-top: 20px;">Faça login para visualizar seu perfil.</p>
        </div>
      </div>
    </div>
  `;

  document.addEventListener("DOMContentLoaded", () => {
    document.body.insertAdjacentHTML("beforeend", modaisHTML);
    carregarDadosPainel();
  });

  window.abrirPainelDireita = function (id) {
    document.getElementById(id).classList.add("aberto");
    carregarDadosPainel();
  };

  window.fecharPainelDireita = function (id) {
    document.getElementById(id).classList.remove("aberto");
  };

  window.encerrarSessao = async function () {
    await fetch("api/auth.php?acao=logout");
    sessionStorage.removeItem("usuarioLogado");
    window.location.href = "index.html";
  };

  window.filtrarAlunosProfessor = function () {
    const nome = document.getElementById("filtroProfNome")?.value || "";
    const turma_id = document.getElementById("filtroProfTurma")?.value || "";
    const turno = document.getElementById("filtroProfTurno")?.value || "";
    carregarDadosPainel({ nome, turma_id, turno }, true);
  };

  window.carregarDadosPainel = async function (filtros = {}, apenasListaAlunos = false) {
    try {
      const params = new URLSearchParams(filtros);
      const resp = await fetch("api/dados_usuario.php?" + params.toString());
      const data = await resp.json();

      if (!data.autenticado) return;

      // Renderiza Notificações
      const divNotif = document.getElementById("listaNotificacoes");
      if (data.notificacoes && data.notificacoes.length > 0) {
        divNotif.innerHTML = data.notificacoes
          .map(
            (n) => `<div class="card-info-lateral">
                      <div>${n.mensagem}</div>
                      <small style="color:#777;">${n.data_envio}</small>
                    </div>`
          )
          .join("");
      } else {
        divNotif.innerHTML = `<p style="color: #666; text-align: center; margin-top: 20px;">sem notificações</p>`;
      }

      // Renderiza Materiais Didáticos
      const divMat = document.getElementById("listaMateriais");
      if (data.materiais && data.materiais.length > 0) {
        divMat.innerHTML = data.materiais
          .map(
            (m) => `<div class="card-info-lateral">
                      <strong>${m.titulo}</strong><br/>
                      <span style="font-size:12px; color:#7607aa;">Conteúdo: ${m.conteudo}</span>
                      <p style="margin:5px 0 0;">${m.descricao}</p>
                    </div>`
          )
          .join("");
      }

      if (apenasListaAlunos && data.usuario.tipo_usuario === "professor") {
        renderizarListaAlunosProfessor(data.perfil.alunos_turmas);
        return;
      }

      // Renderiza Perfil de acordo com o tipo de usuário
      const u = data.usuario;
      const p = data.perfil;
      const divPerfil = document.getElementById("conteudoPerfil");

      let html = `
        <div class="card-info-lateral">
          <span class="badge-perfil">${u.tipo_usuario}</span>
          <h5 style="margin:8px 0 4px; color:#4a148c; font-weight:bold;">${u.nome}</h5>
          <div><strong>Código:</strong> ${u.codigo_identificacao || "-"}</div>
          <div><strong>E-mail:</strong> ${u.email || "Não informado"}</div>
          <div><strong>Escola:</strong> ${u.escola_nome || "Não informada"}</div>
        </div>
      `;

      // VISÃO DO ALUNO
      if (u.tipo_usuario === "aluno" && p.detalhes) {
        html += `
          <h6 style="color:#543583; font-weight:bold; margin-top:12px;">Meu Desempenho</h6>
          <div class="card-info-lateral">
            <div><strong>Turma:</strong> ${p.detalhes.turma || "-"} (${p.detalhes.turno || "-"})</div>
            <div><strong>Pontuação Total:</strong> ${p.detalhes.pontuacao} pts</div>
            <div><strong>Progresso Geral:</strong> ${p.detalhes.progresso}%</div>
            <div><strong>Fases Concluídas:</strong> ${p.detalhes.fases_concluidas}</div>
          </div>
          <h6 style="color:#543583; font-weight:bold; margin-top:12px;">Últimas Atividades</h6>
        `;
        if (p.historico && p.historico.length > 0) {
          html += p.historico
            .map(
              (h) => `<div class="card-info-lateral" style="padding:8px;">
                        <strong>${h.titulo} - Fase ${h.fase}</strong><br/>
                        Acertos: ${h.acertos} | Erros: ${h.erros} | +${h.pontuacao} pts
                      </div>`
            )
            .join("");
        } else {
          html += `<p style="font-size:13px; color:#666;">Nenhuma atividade registrada ainda.</p>`;
        }
      }

      // VISÃO DO RESPONSÁVEL
      if (u.tipo_usuario === "responsavel") {
        html += `<h6 style="color:#543583; font-weight:bold; margin-top:12px;">Alunos Vinculados</h6>`;
        if (p.alunos_vinculados && p.alunos_vinculados.length > 0) {
          html += p.alunos_vinculados
            .map(
              (al) => `<div class="card-info-lateral">
                        <strong>${al.nome}</strong> (${al.codigo_identificacao})<br/>
                        <div><strong>Turma:</strong> ${al.turma || "-"} - ${al.turno || "-"}</div>
                        <div><strong>Pontuação:</strong> ${al.pontuacao} pts</div>
                        <div><strong>Progresso:</strong> ${al.progresso}%</div>
                        <div><strong>Fases Concluídas:</strong> ${al.fases_concluidas}</div>
                      </div>`
            )
            .join("");
        } else {
          html += `<p style="font-size:13px; color:#666;">Nenhum aluno vinculado à sua conta.</p>`;
        }
      }

      // VISÃO DO PROFESSOR
      if (u.tipo_usuario === "professor") {
        const optionsTurmas = (p.turmas || [])
          .map((t) => `<option value="${t.id}">${t.nome} (${t.turno})</option>`)
          .join("");

        html += `
          <h6 style="color:#543583; font-weight:bold; margin-top:12px;">Consultar Desempenho dos Alunos</h6>
          <div class="filtro-prof-box">
            <input type="text" id="filtroProfNome" placeholder="Filtrar por nome do aluno..." oninput="filtrarAlunosProfessor()" />
            <select id="filtroProfTurma" onchange="filtrarAlunosProfessor()">
              <option value="">Todas as minhas turmas</option>
              ${optionsTurmas}
            </select>
            <select id="filtroProfTurno" onchange="filtrarAlunosProfessor()">
              <option value="">Todos os turnos</option>
              <option value="Matutino">Matutino</option>
              <option value="Vespertino">Vespertino</option>
              <option value="Noturno">Noturno</option>
            </select>
          </div>
          <div id="listaAlunosProfessor"></div>
        `;
      }

      html += `<button type="button" class="btn-sair-conta" onclick="encerrarSessao()">Sair da Conta</button>`;
      divPerfil.innerHTML = html;

      if (u.tipo_usuario === "professor") {
        renderizarListaAlunosProfessor(p.alunos_turmas);
      }
    } catch (e) {
      console.error("Erro ao carregar dados do painel:", e);
    }
  };

  function renderizarListaAlunosProfessor(lista) {
    const container = document.getElementById("listaAlunosProfessor");
    if (!container) return;
    if (!lista || lista.length === 0) {
      container.innerHTML = `<p style="font-size:13px; color:#666;">Nenhum aluno encontrado para os filtros selecionados.</p>`;
      return;
    }
    container.innerHTML = lista
      .map(
        (al) => `<div class="card-info-lateral">
                  <strong>${al.nome}</strong> (${al.codigo_identificacao})<br/>
                  <div><strong>Turma:</strong> ${al.turma} | <strong>Turno:</strong> ${al.turno}</div>
                  <div><strong>Pontuação:</strong> ${al.pontuacao} pts | <strong>Progresso:</strong> ${al.progresso}%</div>
                  <div><strong>Fases Concluídas:</strong> ${al.fases_concluidas}</div>
                </div>`
      )
      .join("");
  }
})();