import pandas as pd
import mysql.connector
from datetime import datetime

# Configurações de conexão com o banco de dados MariaDB
db_config = {
    'host': 'localhost',
    'user': 'alexandre',
    'password': 'Bidida_62$$$',  # Substitua pela senha correta do usuário alexandre
    'database': 'gedcom'
}

def atualizar_gedcom_do_ods(caminho_ods):
    print("Lendo o arquivo .ods...")
    df = pd.read_excel(caminho_ods, engine='odf')
    
    # Limpeza básica removendo linhas totalmente vazias de nomes
    df = df.dropna(subset=['NOME'])
    
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()
    
    try:
        print("Processando e atualizando registros na base gedcom...")
        
        for index, row in df.iterrows():
            # Gerando um ID único para o indivíduo baseado no índice ou SEQ
            seq_val = row.get('SEQ')
            if pd.isna(seq_val):
                indi_id = f"I{index + 1}"
            else:
                indi_id = f"I{int(seq_val)}"
                
            nome = str(row['NOME']).strip()
            # Formatando o nome no padrão GEDCOM: Nome /Sobrenome/
            partes_nome = nome.rsplit(' ', 1)
            if len(partes_nome) > 1:
                gedcom_name = f"{partes_nome[0]} /{partes_nome[1]}/"
            else:
                gedcom_name = f"/{nome}/"

            # Tratando gênero/sexo com base no status ou nome (padrão básico U se desconhecido)
            sex = 'U'
            
            # Tratamento de datas de nascimento
            nasc_str = ""
            i_isdead = 0
            
            status = str(row.get('STATUS', '')).upper()
            if 'FALECIDA' in status or 'FALECIDO' in status:
                i_isdead = 1
                
            nasc = row.get('NASCIMENTO')
            if pd.notna(nasc):
                if isinstance(nasc, datetime):
                    nasc_str = nasc.strftime("%d %b %Y").upper()
                else:
                    nasc_str = str(nasc)

            # Construindo o bloco de texto GEDCOM bruto para o indivíduo
            gedcom_record = f"0 @{indi_id}@ INDI\n"
            gedcom_record += f"1 NAME {gedcom_name}\n"
            gedcom_record += f"1 SEX {sex}\n"
            
            if nasc_str:
                gedcom_record += f"1 BIRT\n2 DATE {nasc_str}\n"
                
            if i_isdead:
                gedcom_record += "1 DEAT Y\n"
                
            gedcom_record += "1 CHAN\n2 DATE 27 AUG 2026\n"

            # Inserindo ou atualizando na tabela pgv_individuals
            sql_indi = """
                INSERT INTO pgv_individuals (i_id, i_file, i_rin, i_isdead, i_sex, i_gedcom)
                VALUES (%s, 1, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                i_isdead = VALUES(i_isdead), 
                i_sex = VALUES(i_sex), 
                i_gedcom = VALUES(i_gedcom);
            """
            
            cursor.execute(sql_indi, (indi_id, indi_id, i_isdead, sex, gedcom_record))
            
        conn.commit()
        print(f"Sucesso! {len(df)} registros processados e atualizados na tabela pgv_individuals.")
        
    except Exception as e:
        conn.rollback()
        print(f"Erro durante a atualização: {e}")
        
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    arquivo_ods = "dados.ods"
    atualizar_gedcom_do_ods(arquivo_ods)
