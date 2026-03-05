jQuery(function ($) {
  const $form = $("#cr-form");
  const $resultado = $("#cr-resultado");

  function maskDate(value) {
    const digits = String(value || "")
      .replace(/\D/g, "")
      .slice(0, 8);
    const dd = digits.slice(0, 2);
    const mm = digits.slice(2, 4);
    const yyyy = digits.slice(4, 8);

    if (digits.length <= 2) return dd;
    if (digits.length <= 4) return `${dd}/${mm}`;
    return `${dd}/${mm}/${yyyy}`;
  }

  function maskMoneyBR(value) {
    let v = String(value || "");

    v = v.replace(/[^\d,]/g, "");

    const firstComma = v.indexOf(",");
    if (firstComma !== -1) {
      v =
        v.slice(0, firstComma + 1) + v.slice(firstComma + 1).replace(/,/g, "");
    }

    const parts = v.split(",");
    let intPart = (parts[0] || "").replace(/^0+(?=\d)/, "");
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");

    if (parts.length === 1) return intPart;

    const decPart = String(parts[1] || "").slice(0, 2);
    return `${intPart},${decPart}`;
  }

  $("#data_inicio, #data_fim")
    .attr("inputmode", "numeric")
    .attr("autocomplete", "off")
    .attr("placeholder", "dd/mm/aaaa");

  $("#salario")
    .attr("inputmode", "decimal")
    .attr("autocomplete", "off")
    .attr("placeholder", "Ex: 1.500,00");

  $(document).on("input", "#data_inicio, #data_fim", function () {
    const next = maskDate(this.value);
    if (this.value !== next) this.value = next;
  });

  $(document).on("input", "#salario", function () {
    const next = maskMoneyBR(this.value);
    if (this.value !== next) this.value = next;
  });

  const $boxPeriodosFerias = $("#cr-periodos-ferias");

  let periodosFeriasGerados = [];
  let periodos13Gerados = [];

  let $boxPeriodos13 = $("#cr-periodos-13");

  if ($boxPeriodos13.length === 0) {
    const $label13 = $form
      .find('input[name="verbas[]"][value="decimo_terceiro"]')
      .closest("label");

    $boxPeriodos13 = $(
      '<div id="cr-periodos-13" class="cr-periodos-13" style="margin-left:20px; display:none;"></div>'
    );

    if ($label13.length) {
      $label13.after($boxPeriodos13);
    } else {
      $("#cr-verbas").append($boxPeriodos13);
    }
  }

  function parseBRDate(str) {
    if (!str) return null;
    const m = String(str)
      .trim()
      .match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!m) return null;
    const dd = Number(m[1]);
    const mm = Number(m[2]);
    const yyyy = Number(m[3]);
    const d = new Date(yyyy, mm - 1, dd);

    if (
      d.getFullYear() !== yyyy ||
      d.getMonth() !== mm - 1 ||
      d.getDate() !== dd
    )
      return null;
    return d;
  }

  function formatBRDate(date) {
    const dd = String(date.getDate()).padStart(2, "0");
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    const yyyy = date.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
  }

  function toISODate(date) {
    const dd = String(date.getDate()).padStart(2, "0");
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    const yyyy = date.getFullYear();
    return `${yyyy}-${mm}-${dd}`;
  }

  function addDays(date, days) {
    const d = new Date(date.getTime());
    d.setDate(d.getDate() + days);
    return d;
  }

  function addYears(date, years) {
    const d = new Date(date.getTime());
    d.setFullYear(d.getFullYear() + years);
    return d;
  }

  function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }

  function endOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
  }

  function maxDate(a, b) {
    return a > b ? a : b;
  }

  function minDate(a, b) {
    return a < b ? a : b;
  }

  function contarAvos13(inicio, fim) {
    let avos = 0;

    let cur = startOfMonth(inicio);
    const last = startOfMonth(fim);

    while (cur <= last) {
      const mIni = cur;
      const mFim = endOfMonth(cur);

      const interIni = maxDate(mIni, inicio);
      const interFim = minDate(mFim, fim);

      if (interFim >= interIni) {
        const dias = Math.floor((interFim - interIni) / (24 * 3600 * 1000)) + 1;
        if (dias >= 15) avos += 1;
      }

      cur = new Date(cur.getFullYear(), cur.getMonth() + 1, 1);
    }

    return avos;
  }

  function gerarPeriodosFerias() {
    const inicio = parseBRDate($("#data_inicio").val());
    const fim = parseBRDate($("#data_fim").val());

    if (!inicio || !fim || fim < inicio) {
      periodosFeriasGerados = [];
      $boxPeriodosFerias.empty().hide();
      return [];
    }

    const periodos = [];
    let pIni = new Date(inicio.getTime());
    let idx = 1;

    while (pIni <= fim) {
      const pFim = addDays(addYears(pIni, 1), -1);
      if (pFim > fim) break;

      periodos.push({
        inicio: toISODate(pIni),
        fim: toISODate(pFim),
        label: `Período ${idx} (${formatBRDate(pIni)} - ${formatBRDate(pFim)})`,
      });

      idx += 1;
      pIni = addYears(pIni, 1);
    }

    periodosFeriasGerados = periodos.map((p) => ({
      inicio: p.inicio,
      fim: p.fim,
    }));

    if (periodos.length === 0) {
      $boxPeriodosFerias.html(
        '<div class="cr-help">Nenhum período completo de férias vencidas encontrado para as datas informadas.</div>'
      );
    } else {
      const html = periodos
        .map(
          (p) => `
          <label class="cr-subcheck">
            <input type="checkbox" name="periodos_ferias[]" value="${p.inicio}|${p.fim}">
            ${p.label}
          </label>
        `
        )
        .join("");
      $boxPeriodosFerias.html(html);
    }

    return periodos;
  }

  function gerarPeriodos13() {
    const inicio = parseBRDate($("#data_inicio").val());
    const fim = parseBRDate($("#data_fim").val());

    if (!inicio || !fim || fim < inicio) {
      periodos13Gerados = [];
      $boxPeriodos13.empty().hide();
      return [];
    }

    const periodos = [];

    for (let ano = inicio.getFullYear(); ano <= fim.getFullYear(); ano++) {
      const anoIni = new Date(ano, 0, 1);
      const anoFim = new Date(ano, 11, 31);

      const pIni = maxDate(anoIni, inicio);
      const pFim = minDate(anoFim, fim);

      if (pFim < pIni) continue;

      const avos = contarAvos13(pIni, pFim);
      if (avos <= 0) continue;

      periodos.push({
        inicio: toISODate(pIni),
        fim: toISODate(pFim),
        label: `13º de ${formatBRDate(pIni)} a ${formatBRDate(
          pFim
        )} (${avos}/12 avos)`,
      });
    }

    periodos13Gerados = periodos.map((p) => ({ inicio: p.inicio, fim: p.fim }));

    if (periodos.length === 0) {
      $boxPeriodos13.html(
        '<div class="cr-help">Nenhum período de 13º encontrado para as datas informadas.</div>'
      );
    } else {
      const html = periodos
        .map(
          (p) => `
          <label class="cr-subcheck">
            <input type="checkbox" name="periodos_13[]" value="${p.inicio}|${p.fim}">
            ${p.label}
          </label>
        `
        )
        .join("");

      $boxPeriodos13.html(html);
    }

    return periodos;
  }

  function syncFeriasVencidasUI() {
    const marcado = $form
      .find('input[name="verbas[]"][value="ferias_vencidas"]')
      .is(":checked");

    if (!marcado) {
      $boxPeriodosFerias
        .find('input[name="periodos_ferias[]"]')
        .prop("checked", false);
      $boxPeriodosFerias.empty().hide();
      return;
    }

    gerarPeriodosFerias();
    $boxPeriodosFerias.stop(true, true).slideDown(120);
  }

  function syncDecimoTerceiroUI() {
    const marcado = $form
      .find('input[name="verbas[]"][value="decimo_terceiro"]')
      .is(":checked");

    if (!marcado) {
      $boxPeriodos13.find('input[name="periodos_13[]"]').prop("checked", false);
      $boxPeriodos13.empty().hide();
      return;
    }

    gerarPeriodos13();
    $boxPeriodos13.stop(true, true).slideDown(120);
  }

  $(document).on(
    "change",
    'input[name="verbas[]"][value="ferias_vencidas"]',
    syncFeriasVencidasUI
  );

  $(document).on(
    "change",
    'input[name="verbas[]"][value="decimo_terceiro"]',
    syncDecimoTerceiroUI
  );

  $(document).on("change blur", "#data_inicio, #data_fim", function () {
    const feriasMarcado = $form
      .find('input[name="verbas[]"][value="ferias_vencidas"]')
      .is(":checked");

    if (feriasMarcado) syncFeriasVencidasUI();

    const decimoMarcado = $form
      .find('input[name="verbas[]"][value="decimo_terceiro"]')
      .is(":checked");

    if (decimoMarcado) syncDecimoTerceiroUI();
  });

  syncFeriasVencidasUI();
  syncDecimoTerceiroUI();

  function tipoBloqueiaFGTS(tipo) {
    return ["pedido_demissao", "justa_causa"].includes(String(tipo || ""));
  }

  function syncTipoRescisaoUI() {
    const tipo = $("#tipo").val();
    const bloqueia = tipoBloqueiaFGTS(tipo);

    const $master = $("#cr-fgts-master");
    const $masterLabel = $master.closest("label");
    const $opcoes = $("#cr-fgts-opcoes");

    if (bloqueia) {
      $master.prop("checked", false).prop("disabled", true);
      $opcoes.stop(true, true).hide();
      $opcoes.find("input").prop("checked", false).prop("disabled", true);

      if ($masterLabel.length) $masterLabel.hide();
    } else {
      $master.prop("disabled", false);
      if ($masterLabel.length) $masterLabel.show();

      syncFgtsUI();
    }
  }

  function syncFgtsUI() {
    const master = $("#cr-fgts-master").is(":checked");

    if (master) {
      $("#cr-fgts-opcoes").stop(true, true).slideDown(120);
      $("#cr-fgts-opcoes input").prop("disabled", false);

      const $opts = $('input[name="verbas[]"][value^="fgts_"]');
      const anyChecked = $opts.is(":checked");
      if (!anyChecked) {
        $('input[name="verbas[]"][value="fgts_periodo"]').prop("checked", true);
      }
    } else {
      $("#cr-fgts-opcoes").stop(true, true).slideUp(120);
      $("#cr-fgts-opcoes input").prop("disabled", true);

      $('input[name="verbas[]"][value^="fgts_"]').prop("checked", false);
      $("#cr-fgts-opcoes input").prop("checked", false);
    }
  }

  $(document).on("change", "#cr-fgts-master", syncFgtsUI);

  $(document).on("change", "#tipo", syncTipoRescisaoUI);

  syncFgtsUI();
  syncTipoRescisaoUI();

  const brl = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

  function money(v) {
    const n = Number(v || 0);
    return brl.format(n);
  }

  function escapeHtml(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function renderLista(titulo, itens, tipo) {
    if (!Array.isArray(itens) || itens.length === 0) return "";

    let rows = "";
    for (const it of itens) {
      rows += `
        <div class="cr-row">
          <div class="cr-row__title">${escapeHtml(it.titulo)}</div>
          <div class="cr-row__value ${tipo === "desconto" ? "is-neg" : ""}">
            ${money(it.valor)}
          </div>
        </div>
      `;
    }

    return `
      <div class="cr-card ${titulo === "Proventos" ? "cr-card--full" : ""}">
        <div class="cr-card__title">${escapeHtml(titulo)}</div>
        <div class="cr-card__body">${rows}</div>
      </div>
    `;
  }

  function renderFGTS(fgts) {
    if (!fgts) return "";

    const hasAny =
      Number(fgts.depositado || 0) ||
      Number(fgts.sobre_rescisorias || 0) ||
      Number(fgts.nao_depositado || 0) ||
      Number(fgts.multa || 0) ||
      Number(fgts.saque_total || 0);

    if (!hasAny) return "";

    const rows = [
      ["Depositado", fgts.depositado],
      ["Sobre verbas rescisórias", fgts.sobre_rescisorias],
      ["Estimativa não depositado", fgts.nao_depositado],
      ["Multa", fgts.multa],
      ["Saque total", fgts.saque_total],
    ]
      .filter(([, v]) => Number(v || 0) !== 0)
      .map(
        ([label, v]) => `
          <div class="cr-row">
            <div class="cr-row__title">${escapeHtml(label)}</div>
            <div class="cr-row__value">${money(v)}</div>
          </div>
        `
      )
      .join("");

    return `
      <div class="cr-card cr-card--fgts">
        <div class="cr-card__title">FGTS</div>
        <div class="cr-card__body">${rows}</div>
      </div>
    `;
  }

  function renderResumo(resumo) {
    if (!resumo) return "";
    return `
      <div class="cr-card cr-card--highlight">
        <div class="cr-card__title">Resumo</div>
        <div class="cr-card__body">
          <div class="cr-row">
            <div class="cr-row__title">Total bruto</div>
            <div class="cr-row__value">${money(resumo.total_bruto)}</div>
          </div>
          <div class="cr-row">
            <div class="cr-row__title">Total descontos</div>
            <div class="cr-row__value is-neg">${money(
              resumo.total_descontos
            )}</div>
          </div>
          <div class="cr-row cr-row--big">
            <div class="cr-row__title"><strong>Total líquido</strong></div>
            <div class="cr-row__value"><strong>${money(
              resumo.total_liquido
            )}</strong></div>
          </div>
        </div>
      </div>
    `;
  }

  function renderMensagens(mensagens) {
    if (!Array.isArray(mensagens) || mensagens.length === 0) return "";
    const items = mensagens.map((m) => `<li>${escapeHtml(m)}</li>`).join("");
    return `
      <div class="cr-alert">
        <ul>${items}</ul>
      </div>
    `;
  }

  function renderMeta(meta) {
    if (!meta) return "";
    const t = meta.tempo_servico || {};
    const tempo = `${t.anos ?? 0}a ${t.meses ?? 0}m ${t.dias ?? 0}d`;
    return `
      <div class="cr-meta">
        <div><strong>Tempo de serviço:</strong> ${tempo}</div>
      </div>
    `;
  }

  function renderResultado(data) {
    if (!data || typeof data !== "object") {
      $resultado.html(
        `<div class="cr-alert cr-alert--error">Resposta inválida do servidor.</div>`
      );
      return;
    }

    if (data.erro) {
      $resultado.html(
        `<div class="cr-alert cr-alert--error">${escapeHtml(data.erro)}</div>`
      );
      return;
    }

    const html = `
      <div class="cr-result">
        ${renderMensagens(data.mensagens)}
        ${renderMeta(data.meta)}
        <div class="cr-grid">
          ${renderResumo(data.resumo)}
          ${renderLista("Proventos", data.proventos, "provento")}
          ${renderLista("Descontos", data.descontos, "desconto")}
          ${renderFGTS(data.fgts)}
        </div>
      </div>
    `;

    $resultado.html(html);
  }

  function setLoading(isLoading) {
    const $btn = $("#cr-calcular");
    if (isLoading) {
      $btn.prop("disabled", true).text("Calculando...");
      $resultado.html(`<div class="cr-loading">Calculando…</div>`);
    } else {
      $btn.prop("disabled", false).text("Calcular");
    }
  }

  $form.on("submit", function (e) {
    e.preventDefault();

    setLoading(true);

    const inicioStr = $("#data_inicio").val();
    const fimStr = $("#data_fim").val();
    const salarioStr = $("#salario").val();

    const inicio = parseBRDate(inicioStr);
    const fim = parseBRDate(fimStr);

    const salario = Number(
      String(salarioStr || "")
        .trim()
        .replace(/\./g, "")
        .replace(",", ".")
    );

    if (!inicio || !fim) {
      setLoading(false);
      $resultado.html(
        '<div class="cr-alert cr-alert--error">Informe datas válidas no formato dd/mm/aaaa.</div>'
      );
      return;
    }

    if (fim < inicio) {
      setLoading(false);
      $resultado.html(
        '<div class="cr-alert cr-alert--error">A data do último dia não pode ser menor que a data de início.</div>'
      );
      return;
    }

    if (!salario || salario <= 0) {
      setLoading(false);
      $resultado.html(
        '<div class="cr-alert cr-alert--error">Informe um salário válido (ex: 1500 ou 1.500,00).</div>'
      );
      return;
    }

    const feriasVencidasMarcado = $form
      .find('input[name="verbas[]"][value="ferias_vencidas"]')
      .is(":checked");

    if (feriasVencidasMarcado) {
      const algumPeriodo =
        $form.find('input[name="periodos_ferias[]"]:checked').length > 0;
      if (!algumPeriodo) {
        setLoading(false);
        $resultado.html(
          '<div class="cr-alert cr-alert--error">Selecione pelo menos um período de férias vencidas.</div>'
        );
        return;
      }
    }

    const decimoMarcado = $form
      .find('input[name="verbas[]"][value="decimo_terceiro"]')
      .is(":checked");

    if (decimoMarcado) {
      const algumPeriodo13 =
        $form.find('input[name="periodos_13[]"]:checked').length > 0;
      if (!algumPeriodo13) {
        setLoading(false);
        $resultado.html(
          '<div class="cr-alert cr-alert--error">Selecione pelo menos um período de 13º salário.</div>'
        );
        return;
      }
    }

    const avisoMarcado = $form
      .find('input[name="verbas[]"][value="aviso_previo"]')
      .is(":checked");

    if (avisoMarcado) {
      const avisoTipo = String($form.find('[name="aviso_tipo"]').val() || "");
      if (!avisoTipo) {
        setLoading(false);
        $resultado.html(
          '<div class="cr-alert cr-alert--error">Selecione o tipo de aviso prévio.</div>'
        );
        return;
      }
    }

    let payload = $form.serializeArray();
    payload.push({ name: "action", value: "cr_calcular" });

    payload.push({
      name: "periodosGerados",
      value: JSON.stringify(periodosFeriasGerados || []),
    });
    payload.push({
      name: "periodos13Gerados",
      value: JSON.stringify(periodos13Gerados || []),
    });

    const fgtsMaster = $("#cr-fgts-master").is(":checked");
    if (!fgtsMaster) {
      payload = payload.filter(
        (p) => !(p.name === "verbas[]" && String(p.value).startsWith("fgts_"))
      );
    }

    if (!decimoMarcado) {
      payload = payload.filter((p) => p.name !== "periodos_13[]");
    }

    if (!feriasVencidasMarcado) {
      payload = payload.filter((p) => p.name !== "periodos_ferias[]");
    }

    $.post(cr_ajax.url, payload)
      .done(function (res) {
        renderResultado(res);
      })
      .fail(function (xhr) {
        let msg = "Erro ao calcular. Tente novamente.";
        if (xhr && xhr.responseJSON && xhr.responseJSON.erro)
          msg = xhr.responseJSON.erro;
        $resultado.html(
          `<div class="cr-alert cr-alert--error">${escapeHtml(msg)}</div>`
        );
      })
      .always(function () {
        setLoading(false);
      });
  });
});
