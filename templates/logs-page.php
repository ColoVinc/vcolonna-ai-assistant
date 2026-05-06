<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap vcai-settings">

    <div class="vcai-header rounded-3 mb-4 d-flex align-items-center gap-3 p-4">
        <h1 class="text-white m-0 fs-4"><i class="fa-solid fa-robot"></i> <?php esc_html_e( 'VColonna AI — Log Chiamate', 'vcolonna-ai-assistant' ); ?></h1>
    </div>

    <div class="row g-3 mb-4">
        <div class="col">
            <div class="card text-center">
                <div class="card-body py-3">
                    <span class="vcai-stat-number"><?php echo esc_html( intval( $stats['total_calls'] ) ); ?></span>
                    <span class="vcai-stat-label"><?php esc_html_e( 'Chiamate Totali', 'vcolonna-ai-assistant' ); ?></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body py-3">
                    <span class="vcai-stat-number"><?php echo esc_html( number_format( intval( $stats['total_tokens'] ) ) ); ?></span>
                    <span class="vcai-stat-label"><?php esc_html_e( 'Token Usati', 'vcolonna-ai-assistant' ); ?></span>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center">
                <div class="card-body py-3">
                    <span class="vcai-stat-number"><?php echo esc_html( intval( $stats['total_errors'] ) ); ?></span>
                    <span class="vcai-stat-label"><?php esc_html_e( 'Errori', 'vcolonna-ai-assistant' ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ( $total_items > 0 ) : ?>

        <!-- GRAFICI DASHBOARD -->
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h3 class="fs-6 mb-3"><i class="fa-solid fa-chart-line"></i> <?php esc_html_e( 'Chiamate e Token (ultimi 30 giorni)', 'vcolonna-ai-assistant' ); ?></h3>
                        <canvas id="vcai-chart-daily" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="fs-6 mb-3"><i class="fa-solid fa-chart-pie"></i> <?php esc_html_e( 'Provider', 'vcolonna-ai-assistant' ); ?></h3>
                        <canvas id="vcai-chart-provider" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <button type="button" id="vcai-clear-logs" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-trash"></i> <?php esc_html_e( 'Svuota Log', 'vcolonna-ai-assistant' ); ?>
            </button>
            <span class="text-muted small">
                <?php
                // translators: %d is the total number of log entries
                echo esc_html( sprintf( __( '%d registrazioni totali', 'vcolonna-ai-assistant' ), intval( $total_items ) ) ); ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ( empty( $logs ) ) : ?>
        <div class="card">
            <div class="card-body">
                <p class="mb-0"><?php esc_html_e( 'Nessuna chiamata registrata ancora. Inizia a usare VColonna AI per vedere i log qui.', 'vcolonna-ai-assistant' ); ?></p>
            </div>
        </div>
    <?php else : ?>
        <div class="card p-0">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?php esc_html_e( 'Data', 'vcolonna-ai-assistant' ); ?></th>
                            <th><?php esc_html_e( 'Provider', 'vcolonna-ai-assistant' ); ?></th>
                            <th><?php esc_html_e( 'Prompt Token', 'vcolonna-ai-assistant' ); ?></th>
                            <th><?php esc_html_e( 'Completion Token', 'vcolonna-ai-assistant' ); ?></th>
                            <th><?php esc_html_e( 'Totale', 'vcolonna-ai-assistant' ); ?></th>
                            <th><?php esc_html_e( 'Stato', 'vcolonna-ai-assistant' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $vcai_log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $vcai_log['created_at'] ); ?></td>
                                <td><strong><?php echo esc_html( ucfirst( $vcai_log['provider'] ) ); ?></strong></td>
                                <td><?php echo esc_html( intval( $vcai_log['prompt_tokens'] ) ); ?></td>
                                <td><?php echo esc_html( intval( $vcai_log['completion_tokens'] ) ); ?></td>
                                <td><?php echo esc_html( intval( $vcai_log['prompt_tokens'] ) + intval( $vcai_log['completion_tokens'] ) ); ?></td>
                                <td>
                                    <?php if ( $vcai_log['status'] === 'success' ) : ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check"></i> OK</span>
                                    <?php else : ?>
                                        <span class="badge bg-danger vcai-log-error" style="cursor:pointer;" data-error="<?php echo esc_attr( $vcai_log['error_message'] ); ?>"><i class="fa-solid fa-xmark"></i> <?php esc_html_e( 'Errore', 'vcolonna-ai-assistant' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="card-footer d-flex justify-content-center align-items-center gap-3">
                    <?php
                    $vcai_base_url = admin_url( 'admin.php?page=vcai-logs' );
                    if ( $current > 1 ) :
                    ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $current - 1, $vcai_base_url ) ); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-chevron-left"></i> <?php esc_html_e( 'Precedente', 'vcolonna-ai-assistant' ); ?>
                        </a>
                    <?php endif; ?>

                    <span class="text-muted small">
                        <?php
                        // translators: %1$d is the current page number, %2$d is the total number of pages
                        echo esc_html( sprintf( __( 'Pagina %1$d di %2$d', 'vcolonna-ai-assistant' ), $current, $total_pages ) ); ?>
                    </span>

                    <?php if ( $current < $total_pages ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $current + 1, $vcai_base_url ) ); ?>" class="btn btn-outline-secondary btn-sm">
                            <?php esc_html_e( 'Successiva', 'vcolonna-ai-assistant' ); ?> <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
