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

  const initTinyMCE = () => {
    if (typeof tinymce === 'undefined') {
      console.error('TinyMCE is not loaded');
      return;
    }

    tinymce.init({
      selector: '#form-nova-campanha .tinymce-editor',
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
          // Limpar qualquer conteúdo inicial inválido
          const content = editor.getContent({ format: 'text' }).trim();
          if (content === '' || content === 'p' || content === 'p > strong') {
            editor.setContent('');
          }
        });
      }
    });
  };

  const initFlatpickr = () => {
    if (typeof flatpickr === 'undefined') {
      console.warn('Flatpickr is not loaded');
      return;
    }

    flatpickr('.flatpickr-input', {
      dateFormat: 'd/m/Y',
      altInput: false,
      locale: {
        firstDayOfWeek: 1,
        weekdays: {
          shorthand: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
          longhand: [
            'Domingo',
            'Segunda-feira',
            'Terça-feira',
            'Quarta-feira',
            'Quinta-feira',
            'Sexta-feira',
            'Sábado'
          ]
        },
        months: {
          shorthand: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
          longhand: [
            'Janeiro',
            'Fevereiro',
            'Março',
            'Abril',
            'Maio',
            'Junho',
            'Julho',
            'Agosto',
            'Setembro',
            'Outubro',
            'Novembro',
            'Dezembro'
          ]
        }
      }
    });
  };

  const toggleSubmitState = (button, isLoading) => {
    if (!button) {
      return;
    }

    if (isLoading) {
      button.dataset.originalText = button.textContent;
      button.innerHTML = '<span class="loading-spinner"></span>Enviando...';
      button.disabled = true;
    } else {
      const text = button.dataset.originalText || 'Cadastrar campanha';
      button.textContent = text;
      button.disabled = false;
    }
  };

  const validateForm = (form) => {
    const titulo = form.querySelector('input[name="titulo"]');
    const descricao = form.querySelector('textarea[name="descricao"]');
    const beneficios = form.querySelector('textarea[name="beneficios"]');

    if (!titulo.value.trim()) {
      alert('Informe o título da campanha.');
      titulo.focus();
      return false;
    }

    if (!descricao.value.trim()) {
      alert('Informe a descrição da campanha.');
      descricao.focus();
      return false;
    }

    if (!beneficios.value.trim()) {
      alert('Informe os benefícios esperados.');
      beneficios.focus();
      return false;
    }

    return true;
  };

  const refreshCSRFToken = async () => {
    try {
      // Tentar obter o caminho do plugin do DOM
      let pluginPath = null;
      const forms = document.querySelectorAll('form');
      for (const f of forms) {
        const match = f.action.match(/(.+\/plugins\/ideas)/);
        if (match) {
          pluginPath = match[1];
          break;
        }
      }

      if (!pluginPath) {
        console.warn('Não foi possível detectar o caminho do plugin, usando token existente');
        return null;
      }

      const response = await fetch(pluginPath + '/ajax/get_csrf_token.php', {
        credentials: 'include'
      });

      if (!response.ok) {
        console.warn('Resposta não OK ao obter CSRF token:', response.status);
        return null;
      }

      const data = await response.json();
      if (data.success && data.token) {
        console.log('Novo CSRF token obtido com sucesso');
        return data.token;
      }
    } catch (error) {
      console.warn('Erro ao obter novo CSRF token, usando token existente:', error);
    }
    return null;
  };

  const handleSubmit = (form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      if (!validateForm(form)) {
        return;
      }

      if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
      }

      const submitButton = form.querySelector('button[type="submit"]');
      toggleSubmitState(submitButton, true);

      // Obter um novo CSRF token antes de enviar
      const csrfField = form.querySelector('input[name="_glpi_csrf_token"]');
      const newToken = await refreshCSRFToken();
      if (newToken && csrfField) {
        csrfField.value = newToken;
      }

      const formData = new FormData(form);
      const csrfToken = csrfField ? csrfField.value : '';

      // Garantir que o CSRF token está no FormData
      if (csrfToken && !formData.has('_glpi_csrf_token')) {
        formData.set('_glpi_csrf_token', csrfToken);
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

        const contentType = response.headers.get('content-type') || '';
        const text = await response.text();
        const isJSON = contentType.includes('application/json');
        let data = null;

        if (isJSON) {
          try {
            data = JSON.parse(text);
          } catch (error) {
            console.error('Falha ao analisar JSON da resposta', text);
            throw new Error('Não foi possível interpretar a resposta do servidor.');
          }
        } else {
          console.error('Resposta inesperada do servidor', {
            contentType,
            redirected: response.redirected,
            status: response.status,
            preview: text.slice(0, 200)
          });

          const redirectedToLogin = response.redirected && response.url.includes('login.php');
          const message = redirectedToLogin
            ? 'Sua sessão expirou. Atualize a página e faça login novamente.'
            : 'Não foi possível interpretar a resposta do servidor.';

          throw new Error(message);
        }

        if (!response.ok || !data.success) {
          const message = data && data.message ? data.message : 'Falha ao criar a campanha.';
          throw new Error(message);
        }

        alert(data.message || 'Campanha criada com sucesso!');
        if (data.ticket_link) {
          window.location.href = data.ticket_link;
        }
      } catch (error) {
        alert(error.message || 'Erro ao enviar a campanha.');
      } finally {
        toggleSubmitState(submitButton, false);
      }
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-nova-campanha');
    if (!form) {
      return;
    }

    initTinyMCE();
    initFlatpickr();
    handleSubmit(form);

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

      // Inicializar contadores de caracteres (opcional)
      // FormHelpers.initCharCounters('form-nova-campanha', [
      //   { id: 'descricao', max: 3000 },
      //   { id: 'beneficios', max: 2000 }
      // ]);
    }
  });
})();
