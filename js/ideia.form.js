(function () {
  'use strict';

  const updateCSRFToken = (token) => {
    if (!token) {
      return;
    }

    document.querySelectorAll('input[name="_glpi_csrf_token"]').forEach((input) => {
      input.value = token;
    });

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
      meta.setAttribute('content', token);
    }
  };

  const TIMELINE_STEPS = ['rascunho', 'avaliacao_inicial', 'avaliacao_tecnica', 'comite', 'implementacao'];

  const initTinyMCE = () => {
    if (typeof tinymce === 'undefined') {
      console.error('TinyMCE is not loaded');
      return;
    }

    tinymce.init({
      selector: '#form-nova-ideia .tinymce-editor',
      menubar: false,
      branding: false,
      statusbar: false,
      height: 220,
      language: 'pt_BR',
      plugins: 'lists link table autoresize',
      toolbar: 'undo redo | bold italic underline | bullist numlist | link table | removeformat',
      convert_urls: false,
      entity_encoding: 'raw',
      forced_root_block: 'p',
      force_br_newlines: false,
      force_p_newlines: true,
      valid_elements: '*[*]',
      extended_valid_elements: '*[*]',
      setup: (editor) => {
        editor.on('change', () => {
          editor.save();
        });
        editor.on('init', () => {
          const content = editor.getContent({ format: 'text' }).trim();
          if (content === '' || content === 'p') {
            editor.setContent('');
          }
        });
      }
    });
  };

  const parseDate = (value) => {
    if (!value) {
      return null;
    }

    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
      return null;
    }

    return date;
  };

  const formatDate = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return null;
    }

    return date.toLocaleDateString('pt-BR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });
  };

  const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

  const calculateDaysLeft = (deadline) => {
    if (!(deadline instanceof Date)) {
      return 0;
    }

    const now = new Date();
    const diff = deadline.getTime() - now.getTime();
    const days = Math.ceil(diff / (1000 * 60 * 60 * 24));

    return days > 0 ? days : 0;
  };

  const calculateProgress = (start, end) => {
    if (!(start instanceof Date) || Number.isNaN(start.getTime()) || !(end instanceof Date) || Number.isNaN(end.getTime())) {
      return 0;
    }

    const total = end.getTime() - start.getTime();
    if (total <= 0) {
      return 100;
    }

    const now = new Date();
    const elapsed = clamp(now.getTime() - start.getTime(), 0, total);

    return clamp((elapsed / total) * 100, 0, 100);
  };

  const updateCampaignSummary = (option, preview) => {
    const daysElement = document.querySelector('[data-campaign-days]');
    const deadlineElement = document.querySelector('[data-campaign-deadline]');
    const progressBar = document.querySelector('[data-campaign-progress]');
    const progressLabel = document.querySelector('[data-campaign-progress-label]');

    if (!option || !option.value) {
      if (preview) {
        preview.style.display = 'none';
        preview.innerHTML = '';
      }
      if (daysElement) {
        daysElement.textContent = '00';
      }
      if (deadlineElement) {
        deadlineElement.textContent = '--';
      }
      if (progressBar) {
        progressBar.style.width = '0%';
      }
      if (progressLabel) {
        progressLabel.textContent = '0%';
      }
      return;
    }

    const deadline = parseDate(option.dataset.deadline);
    const start = parseDate(option.dataset.start);
    const deadlineText = formatDate(deadline) ?? 'Não definido';
    const daysLeft = calculateDaysLeft(deadline);
    const progress = calculateProgress(start, deadline);

    if (preview) {
      preview.innerHTML = `
        <h3>${option.textContent}</h3>
        <p><strong>Prazo final:</strong> ${deadlineText}</p>
        <p><strong>Dias restantes:</strong> ${daysLeft.toString().padStart(2, '0')}</p>
      `;
      preview.style.display = 'block';
    }

    if (daysElement) {
      daysElement.textContent = daysLeft.toString().padStart(2, '0');
    }

    if (deadlineElement) {
      deadlineElement.textContent = deadlineText;
    }

    if (progressBar) {
      progressBar.style.width = `${Math.round(progress)}%`;
    }

    if (progressLabel) {
      progressLabel.textContent = `${Math.round(progress)}%`;
    }
  };

  const setupCampaignPreview = (form) => {
    const select = form.querySelector('select[name="campanha_id"]');
    const preview = form.querySelector('[data-campaign-preview]');

    if (!select || !preview) {
      return;
    }

    const updatePreview = () => {
      const option = select.options[select.selectedIndex];
      updateCampaignSummary(option, preview);
    };

    select.addEventListener('change', updatePreview);
    updatePreview();
  };

  const updateWorkflowStage = (stageKey) => {
    const timeline = document.querySelector('[data-timeline]');
    const steps = timeline ? timeline.querySelectorAll('.pulsar-timeline__step') : [];
    const targetIndex = Math.max(TIMELINE_STEPS.indexOf(stageKey), 0);

    steps.forEach((step, index) => {
      step.classList.toggle('is-current', index === targetIndex);
      step.classList.toggle('is-complete', index < targetIndex);
    });

    const checklist = document.querySelectorAll('[data-workflow-step]');
    checklist.forEach((item) => {
      const step = item.getAttribute('data-workflow-step');
      const stepIndex = TIMELINE_STEPS.indexOf(step);
      item.checked = stepIndex !== -1 && stepIndex <= targetIndex;
    });
  };

  const validateForm = (form, action) => {
    const titulo = form.querySelector('input[name="titulo"]');
    const campanha = form.querySelector('select[name="campanha_id"]');
    const problema = form.querySelector('textarea[name="problema_identificado"]');
    const solucao = form.querySelector('textarea[name="solucao_proposta"]');
    const beneficios = form.querySelector('textarea[name="beneficios_resultados"]');
    const authorFields = form.querySelectorAll('[name^="autores["]');

    if (!titulo.value.trim()) {
      alert('Informe o título da ideia.');
      titulo.focus();
      return false;
    }

    if (!campanha.value) {
      alert('Selecione uma campanha.');
      campanha.focus();
      return false;
    }

    if (!problema.value.trim()) {
      alert('Descreva o problema identificado.');
      problema.focus();
      return false;
    }

    if (!solucao.value.trim()) {
      alert('Informe a solução proposta.');
      solucao.focus();
      return false;
    }

    if (!beneficios.value.trim()) {
      alert('Informe os benefícios e resultados esperados.');
      beneficios.focus();
      return false;
    }

    const selectedAuthors = Array.from(authorFields)
      .map((field) => parseInt(field.value, 10))
      .filter((value) => !Number.isNaN(value) && value > 0);

    if (!selectedAuthors.length) {
      alert('Selecione ao menos um autor para a ideia.');
      authorFields[0]?.focus();
      return false;
    }

    const uniqueAuthors = new Set(selectedAuthors);
    if (uniqueAuthors.size !== selectedAuthors.length) {
      alert('Selecione autores diferentes em cada campo.');
      return false;
    }

    return true;
  };

  const handleSubmit = (form) => {
    const actionInput = form.querySelector('input[name="acao"]');
    const submitButton = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const action = actionInput?.value || (activeButton?.dataset.action) || 'enviar';

      if (!validateForm(form, action)) {
        return;
      }

      if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
      }

      if (actionInput) {
        actionInput.value = 'enviar';
      }

      const formData = new FormData(form);
      formData.set('acao', 'enviar');
      const csrfField = form.querySelector('input[name="_glpi_csrf_token"]');
      const csrfToken = csrfField ? csrfField.value : '';

      // Garantir que o CSRF token está no FormData
      if (csrfToken && !formData.has('_glpi_csrf_token')) {
        formData.set('_glpi_csrf_token', csrfToken);
      }

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.dataset.originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="loading-spinner"></span>Enviando...';
      }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        });

        const text = await response.text();
        let data;

        try {
          data = JSON.parse(text);
        } catch (error) {
          console.error('Resposta inválida do servidor', text);
          throw new Error('Não foi possível interpretar a resposta do servidor.');
        }

        if (data && data.csrf_token) {
          updateCSRFToken(data.csrf_token);
        }

        if (!response.ok || !data.success) {
          const message = data && data.message ? data.message : 'Falha ao criar a ideia.';
          throw new Error(message);
        }

        alert(data.message || 'Ideia criada com sucesso!');
        updateWorkflowStage('avaliacao_inicial');

        if (data.ticket_link) {
          window.location.href = data.ticket_link;
        }
      } catch (error) {
        alert(error.message || 'Erro ao enviar a ideia.');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          if (submitButton.dataset.originalText) {
            submitButton.innerHTML = submitButton.dataset.originalText;
            delete submitButton.dataset.originalText;
          }
        }
      }
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-nova-ideia');
    if (!form) {
      return;
    }

    initTinyMCE();
    setupCampaignPreview(form);
    handleSubmit(form);
    updateWorkflowStage('rascunho');

    // Inicializar preview de arquivos (usando FormHelpers se disponível)
    if (typeof FormHelpers !== 'undefined') {
      const anexosInput = form.querySelector('#anexos');
      if (anexosInput) {
        // Criar container para preview
        let previewContainer = form.querySelector('#file-preview-container');
        if (!previewContainer) {
          previewContainer = document.createElement('div');
          previewContainer.id = 'file-preview-container';
          previewContainer.style.display = 'none';
          anexosInput.parentNode.insertBefore(previewContainer, anexosInput.nextSibling);
        }

        // Atualizar preview quando arquivos são selecionados
        anexosInput.addEventListener('change', function () {
          FormHelpers.createFilePreview(anexosInput, 'file-preview-container');
        });
      }

      // Inicializar contadores de caracteres (opcional - pode deixar comentado se não quiser)
      // FormHelpers.initCharCounters('form-nova-ideia', [
      //   { id: 'problema_identificado', max: 2000 },
      //   { id: 'solucao_proposta', max: 2000 },
      //   { id: 'beneficios_resultados', max: 2000 }
      // ]);
    }
  });
})();
