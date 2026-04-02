<?php

/*
  Modelo de Autenticação
  Gerencia login e verificação de usuários*/
 

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/login_security.php';

class Auth
{
    private const MAX_TENTATIVAS_LOGIN = 5;
    private const BLOQUEIO_MINUTOS = 10;
    private const MENSAGEM_LOGIN_INVALIDO = 'Email ou senha inválidos';

    private $db;
    private static $dummyPasswordHash;

    public function __construct($db = null)
    {
        if ($db instanceof PDO) {
            $this->db = $db;
            return;
        }

        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Validar credenciais do admin do restaurante
     */
    public function login($email, $senha)
    {
        $email = trim((string)$email);
        $senha = (string)$senha;
        $this->garantirSchemaSegurancaLogin();

        $usuario = null;
        $senhaOk = false;
        $novaSenhaHash = null;
        $iniciouTransacao = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $iniciouTransacao = true;
            }

            $query = "
                SELECT
                    u.*,
                    r.nome AS restaurante_nome,
                    r.plano,
                    r.status AS restaurante_status,
                    CASE
                        WHEN u.bloqueado_ate IS NOT NULL AND u.bloqueado_ate > NOW() THEN 1
                        ELSE 0
                    END AS login_bloqueado,
                    CASE
                        WHEN u.bloqueado_ate IS NOT NULL AND u.bloqueado_ate <= NOW() THEN 1
                        ELSE 0
                    END AS bloqueio_expirado
                FROM usuarios u
                LEFT JOIN restaurantes r ON u.restaurante_id = r.id
                WHERE LOWER(TRIM(u.email)) = LOWER(TRIM(:email))
                LIMIT 1
                FOR UPDATE
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$usuario) {
                if ($iniciouTransacao) {
                    $this->db->commit();
                }
                $this->executarVerificacaoDummy($senha);
                return $this->respostaFalhaLogin();
            }

            if ((int)($usuario['bloqueio_expirado'] ?? 0) === 1) {
                $this->resetarTentativasLogin((int)$usuario['id']);
                $usuario['tentativas_login'] = 0;
                $usuario['bloqueado_ate'] = null;
                $usuario['login_bloqueado'] = 0;
            }

            if ((int)($usuario['login_bloqueado'] ?? 0) === 1) {
                if ($iniciouTransacao) {
                    $this->db->commit();
                }
                $this->executarVerificacaoDummy($senha);
                return $this->respostaFalhaLogin();
            }

            $senhaBanco = (string)($usuario['senha'] ?? '');
            $senhaBancoEhHash = (int)(password_get_info($senhaBanco)['algo'] ?? 0) !== 0;

            if ($senhaBanco !== '' && password_verify($senha, $senhaBanco)) {
                $senhaOk = true;

                if (password_needs_rehash($senhaBanco, PASSWORD_BCRYPT)) {
                    $novaSenhaHash = password_hash($senha, PASSWORD_BCRYPT);
                }
            }

            if (!$senhaOk && !$senhaBancoEhHash && $senhaBanco !== '' && hash_equals($senhaBanco, $senha)) {
                $senhaOk = true;
                $novaSenhaHash = password_hash($senha, PASSWORD_BCRYPT);
            }

            if ($senhaOk) {
                $this->resetarTentativasLogin((int)$usuario['id'], $novaSenhaHash);
            } else {
                $this->registrarFalhaLogin((int)$usuario['id'], (int)($usuario['tentativas_login'] ?? 0));
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        if ($usuario && $senhaOk) {
            $ehSuperAdmin = intval($usuario['super_admin'] ?? 0) === 1;

            // Verificar status do restaurante
            if (!$ehSuperAdmin && ($usuario['restaurante_status'] ?? '') !== 'ATIVO') {
                return $this->respostaFalhaLogin();
            }

            if (!$ehSuperAdmin && isset($usuario['ativo']) && intval($usuario['ativo']) === 0) {
                return $this->respostaFalhaLogin();
            }

            $perfil = strtoupper(trim((string)($usuario['perfil'] ?? 'USER')));
            if ($perfil === 'GARÇOM') {
                $perfil = 'GARCOM';
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'perfil' => $perfil,
                    'restaurante_id' => $usuario['restaurante_id'],
                    'restaurante_nome' => $usuario['restaurante_nome'],
                    'plano' => $usuario['plano'],
                    'foto' => $usuario['foto'] ?? '',
                    'super_admin' => intval($usuario['super_admin'] ?? 0)
                ],
                // Compatibilidade com consumidores antigos
                'usuario' => [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'perfil' => $perfil,
                    'restaurante_id' => $usuario['restaurante_id'],
                    'restaurante_nome' => $usuario['restaurante_nome'],
                    'plano' => $usuario['plano'],
                    'foto' => $usuario['foto'] ?? '',
                    'super_admin' => intval($usuario['super_admin'] ?? 0)
                ]
            ];
        }

        return $this->respostaFalhaLogin();
    }

    /**
     * Verificar se usuário está logado
     */
    public static function check()
    {
        return isset($_SESSION['usuario_id']) && isset($_SESSION['restaurante_id']);
    }

    /**
     * Verificar permissão de plano
     */
    public static function hasPlano($plano_required)
    {
        if (!self::check()) {
            return false;
        }

        $planos = [
            'BASICO' => 1,
            'PROFISSIONAL' => 2,
            'EMPRESARIAL' => 3
        ];

        $plano_atual = strtoupper($_SESSION['plano'] ?? 'BASICO');
        $plano_req = strtoupper($plano_required);

        return ($planos[$plano_atual] ?? 1) >= ($planos[$plano_req] ?? 1);
    }

    /**
     * Criar primeiro admin (usado na instalação)
     */
    public function criarPrimeiroAdmin($restaurante_id, $nome, $email, $senha)
    {
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

        $query = "INSERT INTO usuarios (restaurante_id, nome, email, senha, perfil, status) 
                  VALUES (:rid, :nome, :email, :senha, 'ADMIN', 'ATIVO')";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':rid', $restaurante_id);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':senha', $senha_hash);

        return $stmt->execute();
    }

    /**
     * Atualizar senha do usuário
     */
    public function atualizarSenha($usuario_id, $nova_senha)
    {
        try {
            $this->garantirSchemaSegurancaLogin();
            $senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);

            $query = "UPDATE usuarios SET senha = :senha, tentativas_login = 0, bloqueado_ate = NULL WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':senha', $senha_hash);
            $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    private function garantirSchemaSegurancaLogin(): void
    {
        if (!login_security_garantir_schema($this->db)) {
            throw new RuntimeException('Nao foi possivel garantir as colunas de seguranca do login.');
        }
    }

    private function resetarTentativasLogin(int $usuarioId, ?string $novaSenhaHash = null): void
    {
        if ($novaSenhaHash !== null) {
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET senha = :senha,
                    tentativas_login = 0,
                    bloqueado_ate = NULL
                WHERE id = :id
            ");
            $stmt->bindValue(':senha', $novaSenhaHash, PDO::PARAM_STR);
        } else {
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET tentativas_login = 0,
                    bloqueado_ate = NULL
                WHERE id = :id
            ");
        }

        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function registrarFalhaLogin(int $usuarioId, int $tentativasAtuais): void
    {
        $novasTentativas = max(0, $tentativasAtuais) + 1;

        if ($novasTentativas >= self::MAX_TENTATIVAS_LOGIN) {
            $sql = sprintf(
                "
                    UPDATE usuarios
                    SET tentativas_login = :tentativas,
                        bloqueado_ate = DATE_ADD(NOW(), INTERVAL %d MINUTE)
                    WHERE id = :id
                ",
                self::BLOQUEIO_MINUTOS
            );
        } else {
            $sql = "
                UPDATE usuarios
                SET tentativas_login = :tentativas,
                    bloqueado_ate = NULL
                WHERE id = :id
            ";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tentativas', $novasTentativas, PDO::PARAM_INT);
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function executarVerificacaoDummy(string $senha): void
    {
        if (!is_string(self::$dummyPasswordHash) || self::$dummyPasswordHash === '') {
            self::$dummyPasswordHash = password_hash('restaurante-saas-login-placeholder', PASSWORD_BCRYPT);
        }

        password_verify($senha, self::$dummyPasswordHash);
    }

    private function respostaFalhaLogin(): array
    {
        return ['success' => false, 'message' => self::MENSAGEM_LOGIN_INVALIDO];
    }
}
